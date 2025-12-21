"""
main.py - Q-Learning Buffer Optimizer Middleware
Akses: http://localhost:8000/docs (Swagger UI)
"""

from fastapi import FastAPI, Query # type: ignore
from pydantic import BaseModel, Field # type: ignore
import numpy as np
import joblib # type: ignore
import redis # type: ignore
import os

app = FastAPI(
    title="ELIOT Q-Learning Buffer Optimizer",
    description="Optimasi ukuran buffer UID menggunakan Q-Learning tabular",
    version="2.0.0"
)

# Load Q-Table
MODEL_DIR = os.path.join(os.path.dirname(__file__), "models")
MODEL_PATH = os.path.join(MODEL_DIR, "uid_buffer_qtable.joblib")

if not os.path.exists(MODEL_PATH):
    raise FileNotFoundError(f"Q-Table tidak ditemukan di {MODEL_PATH}")

q_table = joblib.load(MODEL_PATH)
print(f"[Q-LEARNING] Q-Table loaded: shape {q_table.shape}")

# Redis connection
try:
    redis_client = redis.Redis(host='127.0.0.1', port=6379, db=0, decode_responses=True)
    redis_client.ping()
    print("[Q-LEARNING] Redis connected")
except Exception as e:
    print(f"[Q-LEARNING] Redis connection failed: {e}")
    redis_client = None

class PredictionResponse(BaseModel):
    queue_length: int = Field(..., description="Panjang antrian UID pending")
    current_buffer: int = Field(..., description="Ukuran buffer saat ini")
    action: int = Field(..., description="0=maintain, 1=increase, 2=decrease")
    new_buffer_size: int = Field(..., description="Ukuran buffer baru yang direkomendasikan")
    message: str = Field(..., description="Pesan status")

def get_state_bin(queue_length: int) -> int:
    """Binning: 0-200+ queue → 0-20 state"""
    return min(queue_length // 10, 20)

def predict_action(state_bin: int) -> int:
    """Greedy policy: pilih action dengan Q-value tertinggi"""
    return int(np.argmax(q_table[state_bin]))

@app.get("/predict", response_model=PredictionResponse)
async def predict_buffer(
    queue_length: int = Query(0, ge=0, description="Manual queue length (0 = ambil dari Redis)"),
    current_buffer: int = Query(100, ge=20, le=200, description="Buffer size saat ini")
):
    # Ambil dari Redis jika 0
    if queue_length == 0 and redis_client:
        try:
            queue_length = redis_client.llen('uid_pending_queue')
        except:
            queue_length = 0

    state_bin = get_state_bin(queue_length)
    action = predict_action(state_bin)

    # Action mapping
    if action == 1:
        new_buffer = min(200, current_buffer + 20)
    elif action == 2:
        new_buffer = max(20, current_buffer - 20)
    else:
        new_buffer = current_buffer

    return PredictionResponse(
        queue_length=queue_length,
        current_buffer=current_buffer,
        action=action,
        new_buffer_size=new_buffer,
        message="Prediksi buffer berhasil"
    )

@app.get("/")
async def root():
    return {
        "message": "ELIOT Q-Learning Optimizer aktif!",
        "docs": "/docs",
        "status": "running"
    }

if __name__ == "__main__":
    import uvicorn
    print("Starting Q-Learning middleware on http://localhost:8000")
    uvicorn.run(app, host="0.0.0.0", port=8000)
#!/usr/bin/env python3
"""
Q-Learning.py - Q-Learning Tabular untuk Optimasi Ukuran Buffer UID
Author: Kamu (dengan bantuan Grok)
Tanggal: 21 Desember 2025

Tujuan:
- Agent belajar mengatur ukuran buffer UID secara dinamis
- Minimalkan overflow (UID hilang) dan boros resource
- Hanya menggunakan Gymnasium + NumPy (ringan, tanpa PyTorch)

Fitur:
- Training 10.000 episode
- Simulasi arrival & process UID acak
- Reward design untuk efisiensi dan keamanan
- Simpan Q-Table dengan joblib untuk reuse
- Plot hasil testing
"""

import gymnasium as gym # type: ignore
from gymnasium import spaces # type: ignore
import numpy as np
import matplotlib.pyplot as plt
import random
import joblib # type: ignore
import os

class UidBufferEnv(gym.Env):
    """Environment simulasi buffer UID untuk Q-Learning"""
    
    metadata = {"render_modes": ["human"]}

    def __init__(self, render_mode=None):
        super().__init__()
        
        # State: panjang antrian saat ini (0-200 UID)
        self.observation_space = spaces.Box(low=0, high=200, shape=(1,), dtype=np.float32)
        
        # Action: 0 = tidak ubah, 1 = tambah +20, 2 = kurang -20
        self.action_space = spaces.Discrete(3)
        
        # Batas buffer
        self.maxBufferSize = 200
        self.minBufferSize = 20
        self.bufferSize = 100  # ukuran awal
        
        # State simulasi
        self.queueLength = 0
        self.maxSteps = 1000
        self.currentStep = 0
        
        self.renderMode = render_mode

    def reset(self, seed=None, options=None):
        """Reset environment ke kondisi awal"""
        super().reset(seed=seed)
        self.bufferSize = 100
        self.queueLength = 0
        self.currentStep = 0
        return np.array([self.queueLength], dtype=np.float32), {}

    def step(self, action):
        """Eksekusi satu step: arrival, process, action, reward"""
        self.currentStep += 1

        # Simulasi UID masuk (arrival) dan diproses (service)
        arrival = random.randint(5, 30)   # 5-30 UID masuk per step
        processed = random.randint(10, 40) # backend proses 10-40 UID
        self.queueLength += arrival
        self.queueLength = max(0, self.queueLength - processed)

        # Agent ubah ukuran buffer berdasarkan action
        if action == 1:  # tambah buffer
            self.bufferSize = min(self.maxBufferSize, self.bufferSize + 20)
        elif action == 2:  # kurangi buffer
            self.bufferSize = max(self.minBufferSize, self.bufferSize - 20)
        # action 0 = tidak ubah

        # Hitung reward
        reward = 0
        if self.queueLength > self.bufferSize:
            reward -= 100  # hukuman berat: overflow (UID hilang)
        elif self.queueLength > self.bufferSize * 0.8:
            reward -= 10   # peringatan: hampir penuh
        else:
            reward += 5    # baik: buffer aman

        # Penalti kecil untuk buffer terlalu besar (boros resource)
        reward -= abs(self.bufferSize - 100) * 0.01

        # Bonus kalau antrian rendah (efisien)
        if self.queueLength < 20:
            reward += 10

        terminated = self.currentStep >= self.maxSteps
        truncated = False

        info = {
            'buffer_size': self.bufferSize,
            'queue_length': self.queueLength
        }

        return np.array([self.queueLength], dtype=np.float32), reward, terminated, truncated, info

    def render(self):
        """Tampilkan status saat ini (untuk debugging)"""
        if self.renderMode == "human":
            print(f"Step {self.currentStep} | Queue: {self.queueLength} | Buffer: {self.bufferSize}")

class QLearningAgent:
    """Agent Q-Learning tabular menggunakan NumPy"""
    
    def __init__(self, stateSize=21, actionSize=3, learningRate=0.1, discountFactor=0.99, explorationRate=1.0):
        # Q-Table: [state_bin][action]
        self.qTable = np.zeros((stateSize, actionSize))
        
        self.learningRate = learningRate      # alpha
        self.discountFactor = discountFactor  # gamma
        self.explorationRate = explorationRate  # epsilon
        self.explorationMin = 0.01
        self.explorationDecay = 0.995

    def getStateBin(self, queueLength):
        """Konversi panjang antrian ke bin 0-20 (setiap 10 UID)"""
        return min(int(queueLength // 10), 20)

    def chooseAction(self, stateBin):
        """Epsilon-greedy: explore atau exploit"""
        if random.uniform(0, 1) < self.explorationRate:
            return random.randrange(3)  # explore: acak
        return np.argmax(self.qTable[stateBin])  # exploit: terbaik

    def learn(self, stateBin, action, reward, nextStateBin):
        """Update Q-Table dengan rumus Q-Learning"""
        currentQ = self.qTable[stateBin, action]
        maxFutureQ = np.max(self.qTable[nextStateBin])
        newQ = currentQ + self.learningRate * (reward + self.discountFactor * maxFutureQ - currentQ)
        self.qTable[stateBin, action] = newQ

    def decayExploration(self):
        """Kurangi exploration secara bertahap"""
        if self.explorationRate > self.explorationMin:
            self.explorationRate *= self.explorationDecay

# ========================
# Training & Testing
# ========================

# Direktori untuk simpan Q-Table
MODEL_PATH = '/home/user/Documents/ELIOT/scripts/uid_buffer_qtable.joblib'

env = UidBufferEnv()
agent = QLearningAgent()

episodes = 10000
rewardsHistory = []

print("Mulai training Q-Learning Agent untuk Buffer UID...")

for episode in range(1, episodes + 1):
    stateObs, _ = env.reset()
    stateBin = agent.getStateBin(stateObs[0])
    episodeReward = 0

    for step in range(env.maxSteps):
        action = agent.chooseAction(stateBin)
        nextStateObs, reward, terminated, truncated, _ = env.step(action)
        nextStateBin = agent.getStateBin(nextStateObs[0])

        agent.learn(stateBin, action, reward, nextStateBin)
        stateBin = nextStateBin
        episodeReward += reward

        if terminated or truncated:
            break

    agent.decayExploration()
    rewardsHistory.append(episodeReward)

    if episode % 1000 == 0:
        avgReward = np.mean(rewardsHistory[-1000:])
        print(f"Episode {episode}/{episodes} | Rata-rata Reward: {avgReward:.2f} | Epsilon: {agent.explorationRate:.3f}")

print("Training selesai!")

# Simpan Q-Table dengan joblib
joblib.dump(agent.qTable, MODEL_PATH)
print(f"Q-Table disimpan di: {MODEL_PATH}")

# Testing agent terlatih
stateObs, _ = env.reset()
stateBin = agent.getStateBin(stateObs[0])

queueHistory = []
bufferHistory = []

print("\nTesting agent terlatih (100 step)...")
for step in range(100):
    action = np.argmax(agent.qTable[stateBin])  # greedy policy
    nextStateObs, reward, terminated, truncated, info = env.step(action)
    nextStateBin = agent.getStateBin(nextStateObs[0])

    queueHistory.append(info['queue_length'])
    bufferHistory.append(info['buffer_size'])

    stateBin = nextStateBin
    if terminated or truncated:
        break

# Visualisasi hasil
plt.figure(figsize=(12, 6))
plt.plot(queueHistory, label='Panjang Antrian (Queue Length)', linewidth=2)
plt.plot(bufferHistory, label='Ukuran Buffer Dinamis (Q-Learning)', linewidth=2)
plt.axhline(y=100, color='r', linestyle='--', label='Buffer Tetap (Fixed 100)', linewidth=2)
plt.title('Hasil Q-Learning: Optimasi Buffer UID Secara Dinamis', fontsize=14)
plt.xlabel('Step Waktu', fontsize=12)
plt.ylabel('Jumlah UID', fontsize=12)
plt.legend(fontsize=12)
plt.grid(True, alpha=0.3)
plt.tight_layout()
plt.show()

print("\nContoh Q-Table (10 state pertama):")
print(agent.qTable[:10])
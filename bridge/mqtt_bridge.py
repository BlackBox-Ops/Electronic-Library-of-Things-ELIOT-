#!/usr/bin/env python3
"""
=============================================================================
LibreFlow RFID Bridge - Perpustakaan RFID System
=============================================================================
Middleware Python untuk ESP32 RFID Scanner ke Database MySQL
- Terima data dari MQTT HiveMQ Cloud
- Simpan UID ke tabel uid_buffer
- Cek apakah buku sudah terdaftar
- Alert judul buku di terminal
=============================================================================
"""

import paho.mqtt.client as mqtt # type: ignore
import mysql.connector # type: ignore
from mysql.connector import Error # type: ignore
import json
import time
import ssl
import logging
from datetime import datetime
from colorama import init, Fore, Back, Style

# Initialize colorama for colored terminal output
init(autoreset=True)

# ==================== CONFIGURATION ====================
MQTT_BROKER = "08f8c5c81ead4b76891a57ce301f9b1f.s1.eu.hivemq.cloud"
MQTT_PORT = 8883
MQTT_USER = "ESP32-Etag"
MQTT_PASS = "Etag#2025"
MQTT_TOPIC_READ = "libre_flow/rfid/read"

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'perpustakaan_db'
}

# ==================== LOGGING SETUP ====================
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler('libreflow_bridge.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# ==================== DATABASE HANDLER ====================
class DatabaseHandler:
    def __init__(self, config):
        self.config = config
        self.connection = None

    def connect(self):
        """Connect to MySQL with retry"""
        max_retries = 3
        for i in range(max_retries):
            try:
                self.connection = mysql.connector.connect(**self.config)
                if self.connection.is_connected():
                    db_info = self.connection.get_server_info()
                    logger.info(f"Connected to MySQL Server v{db_info}")
                    
                    cursor = self.connection.cursor()
                    cursor.execute("SELECT DATABASE();")
                    record = cursor.fetchone()
                    logger.info(f"Active database: {record[0]}")
                    cursor.close()
                    
                    print(Fore.GREEN + "✅ Database connection successful!")
                    return True
            except Error as e:
                logger.error(f"DB connection failed (attempt {i+1}/{max_retries}): {e}")
                time.sleep(3)
        
        print(Fore.RED + "❌ Failed to connect to database!")
        return False

    def ensure_connection(self):
        """Pastikan koneksi aktif, reconnect jika perlu"""
        try:
            if not self.connection or not self.connection.is_connected():
                return self.connect()
            return True
        except:
            return self.connect()

    def normalize_uid(self, uid):
        """Hapus spasi dan titik dua, ubah ke uppercase"""
        return uid.replace(" ", "").replace(":", "").upper()

    def check_uid_exists(self, uid):
        """Cek apakah UID sudah ada di uid_buffer"""
        if not self.ensure_connection():
            return False
        
        try:
            cursor = self.connection.cursor() # type: ignore
            cursor.execute(
                "SELECT 1 FROM uid_buffer WHERE uid = %s AND is_deleted = 0 LIMIT 1",
                (uid,)
            )
            exists = cursor.fetchone() is not None
            cursor.close()
            return exists
        except Error as e:
            logger.error(f"Error checking uid_buffer: {e}")
            return False

    def insert_uid_buffer(self, uid, jenis='pending'):
        """Insert UID baru ke uid_buffer"""
        if not self.ensure_connection():
            return False
        
        try:
            cursor = self.connection.cursor() # pyright: ignore[reportOptionalMemberAccess]
            cursor.execute(
                """INSERT INTO uid_buffer (uid, jenis, is_labeled, timestamp) 
                   VALUES (%s, %s, 'no', NOW())""",
                (uid, jenis)
            )
            self.connection.commit() # pyright: ignore[reportOptionalMemberAccess]
            cursor.close()
            
            logger.info(f"✅ UID inserted: {uid} (jenis: {jenis})")
            return True
        except Error as e:
            logger.error(f"Failed to insert UID: {e}")
            return False

    def update_uid_timestamp(self, uid):
        """Update timestamp untuk UID yang sudah ada"""
        if not self.ensure_connection():
            return False
        
        try:
            cursor = self.connection.cursor() # pyright: ignore[reportOptionalMemberAccess]
            cursor.execute(
                "UPDATE uid_buffer SET timestamp = NOW() WHERE uid = %s",
                (uid,)
            )
            self.connection.commit() # pyright: ignore[reportOptionalMemberAccess]
            cursor.close()
            
            logger.info(f"🔄 UID timestamp updated: {uid}")
            return True
        except Error as e:
            logger.error(f"Failed to update timestamp: {e}")
            return False

    def get_book_by_uid(self, uid):
        """Cek apakah UID terdaftar sebagai buku dan ambil infonya"""
        if not self.ensure_connection():
            return None
        
        try:
            cursor = self.connection.cursor(dictionary=True) # type: ignore
            query = """
                SELECT 
                    b.id,
                    b.judul_buku,
                    b.isbn,
                    p.nama_penerbit,
                    rbu.kode_eksemplar,
                    rbu.kondisi,
                    b.lokasi_rak
                FROM rt_book_uid rbu
                JOIN books b ON rbu.book_id = b.id
                LEFT JOIN publishers p ON b.publisher_id = p.id
                JOIN uid_buffer ub ON rbu.uid_buffer_id = ub.id
                WHERE ub.uid = %s 
                  AND rbu.is_deleted = 0 
                  AND b.is_deleted = 0
                LIMIT 1
            """
            cursor.execute(query, (uid,))
            result = cursor.fetchone()
            cursor.close()
            
            return result
        except Error as e:
            logger.error(f"Error checking book: {e}")
            return None

    def close(self):
        """Close database connection"""
        if self.connection and self.connection.is_connected():
            self.connection.close()
            logger.info("Database connection closed")


# ==================== MQTT HANDLER ====================
class MQTTHandler:
    def __init__(self, db_handler):
        self.db = db_handler
        self.client = mqtt.Client()
        self.client.on_connect = self.on_connect
        self.client.on_message = self.on_message
        self.client.on_disconnect = self.on_disconnect

        # TLS configuration untuk HiveMQ Cloud
        self.client.tls_set(cert_reqs=ssl.CERT_NONE)
        self.client.tls_insecure_set(True)
        self.client.username_pw_set(MQTT_USER, MQTT_PASS)

    def on_connect(self, client, userdata, flags, rc):
        """Callback saat koneksi MQTT berhasil"""
        if rc == 0:
            print(Fore.GREEN + "✅ Connected to HiveMQ Cloud!")
            logger.info("Connected to MQTT broker")
            
            client.subscribe(MQTT_TOPIC_READ)
            print(Fore.CYAN + f"📡 Subscribed to: {MQTT_TOPIC_READ}")
            logger.info(f"Subscribed to topic: {MQTT_TOPIC_READ}")
        else:
            print(Fore.RED + f"❌ MQTT connection failed with code: {rc}")
            logger.error(f"MQTT connection failed with code: {rc}")

    def on_disconnect(self, client, userdata, rc):
        """Callback saat koneksi MQTT terputus"""
        if rc != 0:
            print(Fore.YELLOW + "⚠️  MQTT disconnected. Reconnecting...")
            logger.warning("Unexpected MQTT disconnection. Reconnecting...")

    def on_message(self, client, userdata, msg):
        """Callback saat menerima message dari MQTT"""
        try:
            # Parse JSON payload
            payload = json.loads(msg.payload.decode('utf-8'))
            uid_raw = payload.get('uid')
            mode = payload.get('mode', 'readwrite').lower()

            if not uid_raw:
                logger.warning("❌ Received message without UID")
                return

            # Normalize UID
            uid = self.db.normalize_uid(uid_raw)

            # Print header
            print("\n" + "="*70)
            print(Fore.YELLOW + Style.BRIGHT + f"📇 RFID SCAN DETECTED")
            print("="*70)
            print(f"⏰ Time    : {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
            print(f"🔖 UID     : {Fore.CYAN}{uid}")
            print(f"🔧 Mode    : {Fore.MAGENTA}{mode.upper()}")
            print("-"*70)

            # Process berdasarkan mode
            if mode == 'write':
                self.handle_write_mode(uid)
            else:
                self.handle_readwrite_mode(uid)

            print("="*70 + "\n")

        except json.JSONDecodeError as e:
            logger.error(f"Invalid JSON payload: {e}")
        except Exception as e:
            logger.error(f"Error processing message: {e}", exc_info=True)

    def handle_write_mode(self, uid):
        """Handle mode WRITE (Registrasi buku baru)"""
        print(Fore.YELLOW + "📝 Mode: WRITE (Registrasi)")
        
        # Cek apakah UID sudah ada
        if self.db.check_uid_exists(uid):
            print(Fore.RED + "❌ UID sudah terdaftar sebelumnya!")
            
            # Cek apakah UID ini buku
            book = self.db.get_book_by_uid(uid)
            if book:
                print(Fore.YELLOW + "📚 Buku sudah terdaftar:")
                print(f"   Judul: {book['judul_buku']}")
                print(f"   Kode : {book['kode_eksemplar']}")
            return
        
        # Insert UID baru sebagai pending
        if self.db.insert_uid_buffer(uid, 'pending'):
            print(Fore.GREEN + "✅ UID baru berhasil didaftarkan!")
            print(Fore.CYAN + "💡 Silakan assign ke buku di aplikasi web")
        else:
            print(Fore.RED + "❌ Gagal menyimpan UID ke database")

    def handle_readwrite_mode(self, uid):
        """Handle mode R/W (Scan normal)"""
        print(Fore.CYAN + "🔍 Mode: READ/WRITE (Scan)")
        
        # Cek apakah buku sudah terdaftar
        book = self.db.get_book_by_uid(uid)
        
        if book:
            # BUKU DITEMUKAN - ALERT TERMINAL
            print(Fore.GREEN + Style.BRIGHT + "="*70)
            print(Fore.WHITE + Back.GREEN + Style.BRIGHT + " 📚 BUKU TERDAFTAR ".center(70))
            print(Fore.GREEN + Style.BRIGHT + "="*70)
            print(f"{Fore.WHITE}Judul       : {Fore.YELLOW}{Style.BRIGHT}{book['judul_buku']}")
            print(f"{Fore.WHITE}Kode        : {Fore.CYAN}{book['kode_eksemplar']}")
            print(f"{Fore.WHITE}ISBN        : {Fore.MAGENTA}{book['isbn'] or '-'}")
            print(f"{Fore.WHITE}Penerbit    : {Fore.BLUE}{book['nama_penerbit'] or '-'}")
            print(f"{Fore.WHITE}Kondisi     : {self.get_kondisi_color(book['kondisi'])}{book['kondisi'].upper()}")
            print(f"{Fore.WHITE}Lokasi Rak  : {Fore.GREEN}{book['lokasi_rak'] or '-'}")
            print(Fore.GREEN + Style.BRIGHT + "="*70)
            
            # Update timestamp
            self.db.update_uid_timestamp(uid)
            
        else:
            # Buku belum terdaftar
            print(Fore.YELLOW + "⚠️  Buku belum terdaftar")
            
            # Cek apakah UID ada di buffer
            if self.db.check_uid_exists(uid):
                print(Fore.CYAN + "💡 UID sudah ada di buffer, belum di-assign ke buku")
                self.db.update_uid_timestamp(uid)
            else:
                # Insert UID baru
                if self.db.insert_uid_buffer(uid, 'pending'):
                    print(Fore.GREEN + "✅ UID baru ditambahkan ke buffer")
                    print(Fore.CYAN + "💡 Silakan daftarkan di mode WRITE atau web app")

    def get_kondisi_color(self, kondisi):
        """Return color based on kondisi buku"""
        kondisi_map = {
            'baik': Fore.GREEN,
            'rusak_ringan': Fore.YELLOW,
            'rusak_berat': Fore.RED,
            'hilang': Fore.RED + Style.BRIGHT
        }
        return kondisi_map.get(kondisi, Fore.WHITE)

    def connect(self):
        """Connect ke MQTT broker"""
        try:
            print(Fore.CYAN + f"🔌 Connecting to MQTT broker: {MQTT_BROKER}:{MQTT_PORT}")
            self.client.connect(MQTT_BROKER, MQTT_PORT, keepalive=60)
            return True
        except Exception as e:
            print(Fore.RED + f"❌ MQTT connection error: {e}")
            logger.error(f"MQTT connect error: {e}")
            return False

    def start(self):
        """Start MQTT loop"""
        print(Fore.GREEN + Style.BRIGHT + "\n🚀 LibreFlow RFID Bridge Started!")
        print(Fore.CYAN + "📡 Waiting for RFID scans...\n")
        logger.info("MQTT Bridge started")
        
        try:
            self.client.loop_forever()
        except KeyboardInterrupt:
            print(Fore.YELLOW + "\n\n⏹️  Bridge stopped by user (Ctrl+C)")
            logger.info("Bridge stopped by user")


# ==================== MAIN ====================
def print_banner():
    """Print startup banner"""
    banner = f"""
{Fore.CYAN}{Style.BRIGHT}
╔═══════════════════════════════════════════════════════════════════╗
║                                                                   ║
║        📚 LibreFlow RFID Bridge - Perpustakaan System 📚         ║
║                                                                   ║
║                    ESP32 → MQTT → MySQL                          ║
║                                                                   ║
╚═══════════════════════════════════════════════════════════════════╝
{Style.RESET_ALL}
{Fore.YELLOW}Database    : {DB_CONFIG['database']}
{Fore.YELLOW}MQTT Broker : {MQTT_BROKER}
{Fore.YELLOW}Topic       : {MQTT_TOPIC_READ}
{Fore.YELLOW}Version     : 1.0.0
"""
    print(banner)


def main():
    print_banner()
    
    # Initialize database handler
    print(Fore.CYAN + "🗄️  Initializing database connection...")
    db = DatabaseHandler(DB_CONFIG)
    
    if not db.connect():
        print(Fore.RED + "❌ Cannot start bridge: Database connection failed")
        logger.critical("Cannot start bridge: Database connection failed")
        return

    # Initialize MQTT handler
    print(Fore.CYAN + "📡 Initializing MQTT connection...")
    mqtt_handler = MQTTHandler(db)
    
    if not mqtt_handler.connect():
        print(Fore.RED + "❌ Cannot start bridge: MQTT connection failed")
        logger.critical("Cannot start bridge: MQTT connection failed")
        return

    # Start MQTT loop
    try:
        mqtt_handler.start()
    except KeyboardInterrupt:
        print(Fore.YELLOW + "\n\n⏹️  Shutting down gracefully...")
    finally:
        db.close()
        print(Fore.GREEN + "✅ Database connection closed")
        print(Fore.CYAN + "👋 Goodbye!\n")


if __name__ == "__main__":
    main()
#!/usr/bin/env python3
"""
invalidate_old_uid.py - Script untuk invalidate UID expired di database.
Versi diperbaiki: Tambah robust logging, directory creation, argparse untuk opsi, dan detail error handling.

Cara run manual:
- python invalidate_old_uid.py  (run normal)
- python invalidate_old_uid.py --dry-run  (test tanpa update DB)

Cronjob: Jalankan setiap 5 menit.
"""

import mysql.connector # type: ignore
from mysql.connector import errorcode # type: ignore
import datetime
import os
import argparse

# Config DB (ganti sesuai kebutuhan)
config = {
    'user': 'root',
    'password': '',  # Isi password jika ada
    'host': '127.0.0.1',
    'port': 3306,
    'database': 'perpustakaan_db',
    'raise_on_warnings': True
}

# Path log (ganti jika perlu)
log_dir = '/home/user/Documents/ELIOT/logs'
log_path = os.path.join(log_dir, 'invalidate_uid.log')

# SQL Query (tetap sama, tapi bisa di-custom jika perlu)
sql = """
    UPDATE uid_buffer 
    SET jenis = 'invalid', 
        updated_at = NOW() 
    WHERE jenis = 'pending' 
        AND is_labeled = 'no' 
        AND timestamp < NOW() - INTERVAL 5 MINUTE
"""

def ensure_log_dir():
    """Buat directory log jika belum ada."""
    if not os.path.exists(log_dir):
        try:
            os.makedirs(log_dir)
            print(f"Directory log dibuat: {log_dir}")
        except Exception as e:
            print(f"Gagal buat directory log: {str(e)}")
            raise

def log_message(message):
    """Fungsi logging ke file dan console."""
    timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    full_msg = f"[{timestamp}] {message}"
    print(full_msg)
    try:
        with open(log_path, 'a') as log_file:
            log_file.write(full_msg + "\n")
    except Exception as e:
        print(f"Gagal tulis log: {str(e)}")

def main(dry_run=False):
    ensure_log_dir()
    
    log_message("Memulai script invalidate_old_uid.py")
    log_message(f"Mode: {'Dry-run (test)' if dry_run else 'Normal (update DB)'}")
    log_message(f"Query yang akan dijalankan: {sql}")

    try:
        conn = mysql.connector.connect(**config)
        log_message("Koneksi DB berhasil")
        
        cursor = conn.cursor()
        
        if dry_run:
            # Dry-run: Ganti ke SELECT untuk count saja
            count_sql = sql.replace("UPDATE", "SELECT COUNT(*) FROM").replace("SET jenis = 'invalid', updated_at = NOW()", "")
            cursor.execute(count_sql)
            affected = cursor.fetchone()[0]
            log_message(f"Dry-run: {affected} UID akan di-invalidate jika run normal")
        else:
            cursor.execute(sql)
            affected = cursor.rowcount
            conn.commit()
            log_message(f"Berhasil invalidate {affected} UID")

    except mysql.connector.Error as err:
        if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
            log_message("Error: Username atau password salah")
        elif err.errno == errorcode.ER_BAD_DB_ERROR:
            log_message("Error: Database tidak ditemukan")
        else:
            log_message(f"DB Error: {err}")
        raise  # Biar cron tahu ada error

    except Exception as e:
        log_message(f"General Error: {str(e)}")
        raise

    finally:
        if 'cursor' in locals():
            cursor.close()
        if 'conn' in locals() and conn.is_connected():
            conn.close()
            log_message("Koneksi DB ditutup")
        
        log_message("Script selesai")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Invalidate old UID script")
    parser.add_argument('--dry-run', action='store_true', help="Run tanpa update DB (test mode)")
    args = parser.parse_args()
    
    try:
        main(dry_run=args.dry_run)
    except Exception as e:
        log_message(f"Script gagal: {str(e)}")
        exit(1)  # Exit dengan error code untuk cron
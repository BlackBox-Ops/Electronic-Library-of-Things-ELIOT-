#!/usr/bin/env python3
# invalidate_old_uid.py - Final Version dengan support 'invalid'

import mysql.connector # type: ignore
from mysql.connector import errorcode # type: ignore
import datetime

# Config DB
config = {
    'user': 'root',
    'password': '',                    # isi kalau ada password
    'host': '127.0.0.1',
    'port': 3306,
    'database': 'perpustakaan_db',
    'raise_on_warnings': True
}

log_path = '/home/user/Documents/ELIOT/logs/invalidate_uid.log'

try:
    conn = mysql.connector.connect(**config)
    cursor = conn.cursor()

    # Query sekarang aman: set ke 'invalid'
    sql = """
    UPDATE uid_buffer 
    SET jenis = 'invalid', 
        updated_at = NOW() 
    WHERE jenis = 'pending' 
      AND is_labeled = 'no' 
      AND timestamp < NOW() - INTERVAL 5 MINUTE
    """

    cursor.execute(sql)
    affected = cursor.rowcount

    message = f"{datetime.datetime.now()} - Ran cron: {affected} UIDs invalidated (set to 'invalid')."
    print(message)

    with open(log_path, 'a') as log_file:
        log_file.write(message + "\n")

    with open(log_path, 'a') as log_file:
        log_file.write(message + "\n")

    conn.commit()

except mysql.connector.Error as err:
    error_msg = f"{datetime.datetime.now()} - DB Error: {err}"
    print(error_msg)
    with open(log_path, 'a') as log_file:
        log_file.write(error_msg + "\n")
except Exception as e:
    error_msg = f"{datetime.datetime.now()} - General Error: {str(e)}"
    print(error_msg)
    with open(log_path, 'a') as log_file:
        log_file.write(error_msg + "\n")
finally:
    if 'cursor' in locals():
        cursor.close()
    if 'conn' in locals() and conn.is_connected():
        conn.close()
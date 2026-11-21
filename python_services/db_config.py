import os
import mysql.connector

def get_db_connection():
    return mysql.connector.connect(
        host=os.getenv("MYSQLHOST", "127.0.0.1"),
        port=int(os.getenv("MYSQLPORT", 3306)),
        user=os.getenv("MYSQLUSER", "root"),
        password=os.getenv("MYSQLPASSWORD", "0110"),
        database=os.getenv("MYSQLDATABASE", "vms_db")
    )
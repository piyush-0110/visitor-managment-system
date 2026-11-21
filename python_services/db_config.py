import os
import mysql.connector

def get_db_connection():
    config = {
        "host": os.getenv("MYSQLHOST", "127.0.0.1"),
        "port": int(os.getenv("MYSQLPORT", 3306)),
        "user": os.getenv("MYSQLUSER", "root"),
        "password": os.getenv("MYSQLPASSWORD", "0110"),  # <-- Same local password
        "database": os.getenv("MYSQLDATABASE", "vms_db"),
    }
    if os.getenv("MYSQLHOST"):
        config["ssl_ca"] = "/etc/ssl/certs/ca-certificates.crt"

    return mysql.connector.connect(**config)
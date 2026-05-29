from dotenv import load_dotenv
import os
import pymysql
from urllib.parse import urlparse


load_dotenv(dotenv_path="../.env.local")  # adapter si besoin

def get_db_connection():
    url = os.getenv("DATABASE_URL")
    if not url:
        raise ValueError("DATABASE_URL non définie")
    parsed = urlparse(url)
    conn = pymysql.connect(
        host=parsed.hostname,
        user=parsed.username,
        password=parsed.password,
        database=parsed.path.lstrip('/'),
        port=parsed.port or 3306,
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor
    )
    return conn

from dotenv import load_dotenv
import os
import pymysql
from datetime import datetime
from common.db import get_db_connection


def update_job(job_id, status, result_path=None, result_format=None, result_size=None, duration_sec=None):
    conn = get_db_connection()

    with conn.cursor() as cursor:
        sql = """
              UPDATE generation_job
              SET status        = %s,
                  finished_at   = %s,
                  result_path   = %s,
                  result_format = %s,
                  result_size   = %s,
                  duration_sec  = %s,
                  updated_at    = %s
              WHERE id = %s \
              """
        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

        cursor.execute(sql, (
            status,
            now,
            result_path,
            result_format,
            result_size,
            duration_sec,
            now,
            job_id
        ))

        conn.commit()
    conn.close()

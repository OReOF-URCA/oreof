#!/usr/bin/env python3

import sys
import pandas as pd
from common.db import get_db_connection
import time
from common.job_tracker import update_job
import os

# Lire args
job_id = sys.argv[1]

start_time = time.time()

# Connexion DB
conn = get_db_connection()


# Récupération des formations
with conn.cursor() as cursor:
    sql = "SELECT id, objectifs_formation FROM formation ORDER BY created DESC"
    cursor.execute(sql)
    formations = cursor.fetchall()

conn.close()

# Debug : afficher en console
print(f"Nombre de formations : {len(formations)}")

# Génération Excel
df = pd.DataFrame(formations)
output_dir = os.path.join(os.path.dirname(__file__), '..', 'public', 'export')
os.makedirs(output_dir, exist_ok=True)
output_path = os.path.join(output_dir, 'formations.xlsx')

df.to_excel(output_path, index=False)

# Temps total
duration_sec = int(time.time() - start_time)

# Mettre à jour la table generation_job
update_job(
    job_id,
    status='finished',
    result_path='/export/formations.xlsx',  # à adapter
    result_format='xlsx',
    result_size=os.path.getsize(output_path),
    duration_sec=duration_sec
)

print(f"✅ Fichier Excel généré : {output_path}")

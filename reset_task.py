import mysql.connector
conn = mysql.connector.connect(host='localhost', user='root', password='', database='ecom')
cursor = conn.cursor()
cursor.execute("UPDATE tbl_ai_tasks SET status = 'PENDING' WHERE id = 1")
conn.commit()
cursor.close()
conn.close()
print('Reset task 1 to PENDING')

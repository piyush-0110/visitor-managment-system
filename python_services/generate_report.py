import os
import sys
import csv
import mysql.connector
from datetime import datetime
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib import colors
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer
from reportlab.lib.styles import getSampleStyleSheet

def get_db_connection():
    host = os.environ.get("DB_HOST", "127.0.0.1")
    port = int(os.environ.get("DB_PORT", 3306))
    user = os.environ.get("DB_USER", "root")
    password = os.environ.get("DB_PASS", "")
    database = os.environ.get("DB_NAME", "test")

    conn_args = {
        "host": host,
        "port": port,
        "user": user,
        "password": password,
        "database": database,
    }
    if "tidbcloud.com" in host:
        conn_args["ssl_disabled"] = False

    return mysql.connector.connect(**conn_args)

def fetch_visits():
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    query = """
        SELECT v.visit_id,
               vis.name AS visitor_name,
               vis.phone AS visitor_phone,
               vis.organization,
               v.purpose,
               COALESCE(u.name, v.custom_host_name, 'Unassigned') AS host_name,
               v.status,
               v.check_in_time,
               v.check_out_time,
               v.created_at
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.visitor_id
        LEFT JOIN users u ON v.host_id = u.user_id
        ORDER BY v.visit_id DESC
    """
    cursor.execute(query)
    rows = cursor.fetchall()
    cursor.close()
    conn.close()
    return rows

def generate_csv(output_path, rows):
    headers = ["Visit ID", "Visitor Name", "Phone", "Organization", "Purpose", "Host", "Status", "Check-In", "Check-Out", "Created At"]
    with open(output_path, mode="w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(headers)
        for r in rows:
            writer.writerow([
                r["visit_id"],
                r["visitor_name"],
                r["visitor_phone"],
                r["organization"] or "—",
                r["purpose"],
                r["host_name"],
                str(r["status"]).upper(),
                str(r["check_in_time"] or "—"),
                str(r["check_out_time"] or "—"),
                str(r["created_at"])
            ])

def generate_pdf(output_path, rows):
    doc = SimpleDocTemplate(output_path, pagesize=landscape(A4), leftMargin=24, rightMargin=24, topMargin=24, bottomMargin=24)
    styles = getSampleStyleSheet()
    elements = []

    title = Paragraph("<b>Visitor Management System (VMS) — Official Visitor Log Report</b>", styles["Title"])
    subtitle = Paragraph(f"Generated on: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} | Total Records: {len(rows)}", styles["Normal"])
    elements.extend([title, Spacer(1, 6), subtitle, Spacer(1, 14)])

    table_data = [["ID", "Visitor Name", "Phone", "Purpose", "Host", "Status", "Check-In", "Check-Out"]]
    for r in rows:
        table_data.append([
            str(r["visit_id"]),
            str(r["visitor_name"])[:22],
            str(r["visitor_phone"]),
            str(r["purpose"])[:24],
            str(r["host_name"])[:20],
            str(r["status"]).upper(),
            str(r["check_in_time"] or "—")[:16],
            str(r["check_out_time"] or "—")[:16],
        ])

    tbl = Table(table_data, repeatRows=1)
    tbl.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#111827")),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTSIZE", (0, 0), (-1, -1), 8.5),
        ("GRID", (0, 0), (-1, -1), 0.5, colors.HexColor("#CBD5E1")),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#F8FAFC")]),
        ("PADDING", (0, 0), (-1, -1), 5),
    ]))
    elements.append(tbl)
    doc.build(elements)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("ERROR: Usage: generate_report.py <csv|pdf> <output_path>")
        sys.exit(1)

    fmt = sys.argv[1].lower().strip()
    out_path = sys.argv[2]
    os.makedirs(os.path.dirname(out_path), exist_ok=True)

    try:
        data = fetch_visits()
        if fmt == "csv":
            generate_csv(out_path, data)
        elif fmt == "pdf":
            generate_pdf(out_path, data)
        else:
            print("ERROR: Invalid format")
            sys.exit(1)
        print("SUCCESS")
    except Exception as e:
        print(f"ERROR: {e}")
        sys.exit(1)
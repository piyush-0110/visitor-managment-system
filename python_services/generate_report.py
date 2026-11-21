import sys
import os
import warnings
import pandas as pd
from reportlab.lib.pagesizes import letter
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer
from reportlab.lib.styles import getSampleStyleSheet
from reportlab.lib import colors
from db_config import get_db_connection

warnings.filterwarnings("ignore")


def export_report(fmt: str):
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    query = """
        SELECT v.visit_id AS 'Visit ID',
               vis.name AS 'Visitor Name',
               vis.phone AS 'Phone',
               COALESCE(vis.organization, 'N/A') AS 'Organization',
               COALESCE(u.name, v.custom_host_name, 'Other') AS 'Host',
               v.purpose AS 'Purpose',
               v.status AS 'Status',
               COALESCE(DATE_FORMAT(v.check_in_time, '%Y-%m-%d %H:%i'), '-') AS 'Check In',
               COALESCE(DATE_FORMAT(v.check_out_time, '%Y-%m-%d %H:%i'), '-') AS 'Check Out'
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.visitor_id
        LEFT JOIN users u ON v.host_id = u.user_id
        ORDER BY v.visit_id DESC
    """
    cursor.execute(query)
    rows = cursor.fetchall()
    cursor.close()
    conn.close()

    columns = [
        "Visit ID", "Visitor Name", "Phone", "Organization",
        "Host", "Purpose", "Status", "Check In", "Check Out"
    ]
    df = pd.DataFrame(rows, columns=columns)

    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    report_dir = os.path.join(base_dir, "assets", "reports")
    os.makedirs(report_dir, exist_ok=True)

    if fmt == "csv":
        out_path = os.path.join(report_dir, "visitor_report.csv")
        df.to_csv(out_path, index=False)
        print("assets/reports/visitor_report.csv")
    elif fmt == "pdf":
        out_path = os.path.join(report_dir, "visitor_report.pdf")
        doc = SimpleDocTemplate(out_path, pagesize=letter)
        styles = getSampleStyleSheet()
        elements = [
            Paragraph("Visitor Management System — Activity Report", styles["Title"]),
            Spacer(1, 12),
        ]

        if df.empty:
            elements.append(Paragraph("No visitor records found.", styles["Normal"]))
        else:
            data = [df.columns.tolist()] + df.astype(str).values.tolist()
            table = Table(data, repeatRows=1)
            table.setStyle(TableStyle([
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#2563eb")),
                ("TEXTCOLOR", (0, 0), (-1, 0), colors.whitesmoke),
                ("FONTSIZE", (0, 0), (-1, -1), 8),
                ("GRID", (0, 0), (-1, -1), 0.5, colors.grey),
                ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#f1f5f9")]),
            ]))
            elements.append(table)

        doc.build(elements)
        print("assets/reports/visitor_report.pdf")


if __name__ == "__main__":
    fmt = sys.argv[1].lower() if len(sys.argv) > 1 else "csv"
    export_report(fmt)
import os
import sys
import csv
import json
from datetime import datetime
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib import colors
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer
from reportlab.lib.styles import getSampleStyleSheet

def generate_csv(output_path, rows):
    headers = ["Visit ID", "Visitor Name", "Phone", "Organization", "Purpose", "Host", "Status", "Check-In", "Check-Out", "Created At"]
    with open(output_path, mode="w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(headers)
        for r in rows:
            writer.writerow([
                r.get("visit_id", ""),
                r.get("visitor_name", ""),
                r.get("visitor_phone", ""),
                r.get("organization") or "—",
                r.get("purpose", ""),
                r.get("host_name") or "Unassigned",
                str(r.get("status", "")).upper(),
                str(r.get("check_in_time") or "—"),
                str(r.get("check_out_time") or "—"),
                str(r.get("created_at", ""))
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
            str(r.get("visit_id", "")),
            str(r.get("visitor_name", ""))[:22],
            str(r.get("visitor_phone", "")),
            str(r.get("purpose", ""))[:24],
            str(r.get("host_name") or "Unassigned")[:20],
            str(r.get("status", "")).upper(),
            str(r.get("check_in_time") or "—")[:16],
            str(r.get("check_out_time") or "—")[:16],
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
    if len(sys.argv) < 4:
        print("ERROR: Usage: generate_report.py <csv|pdf> <output_path> <json_data_path>")
        sys.exit(1)

    fmt = sys.argv[1].lower().strip()
    out_path = sys.argv[2]
    json_path = sys.argv[3]
    os.makedirs(os.path.dirname(out_path), exist_ok=True)

    try:
        with open(json_path, "r", encoding="utf-8") as jf:
            data = json.load(jf)

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
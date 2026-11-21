import sys
import os
import qrcode

if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.exit(1)

    visit_id = sys.argv[1]
    visitor_name = sys.argv[2] if len(sys.argv) > 2 else "Visitor"

    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    output_dir = os.path.join(base_dir, "assets", "qr_codes")
    os.makedirs(output_dir, exist_ok=True)

    qr_payload = f"VMS-PASS | ID: #{visit_id} | Name: {visitor_name}"
    img = qrcode.make(qr_payload)
    file_path = os.path.join(output_dir, f"visit_{visit_id}.png")
    img.save(file_path)

    print(f"assets/qr_codes/visit_{visit_id}.png")
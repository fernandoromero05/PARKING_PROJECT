from fastapi import FastAPI
from pydantic import BaseModel
import re

app = FastAPI()

class CodeIn(BaseModel):
    code: str

# Format:
# LOT_A-12   (lot code + "-" + spot number)
CODE_RE = re.compile(r"^(LOT_[A-Z])-(\d{1,3})$")

@app.post("/validate_code")
def validate_code(payload: CodeIn):
    code = payload.code.strip().upper()
    m = CODE_RE.match(code)
    if not m:
        return {"ok": False, "error": "Invalid code format. Use LOT_A-12 style."}

    lot_code = m.group(1)
    spot_number = int(m.group(2))

    # Simple bounds per lot for MVP
    bounds = {"LOT_A": 32, "LOT_B": 16, "LOT_C": 12}
    if lot_code not in bounds:
        return {"ok": False, "error": "Unknown lot"}
    if spot_number < 1 or spot_number > bounds[lot_code]:
        return {"ok": False, "error": "Spot out of range"}

    return {"ok": True, "lot_code": lot_code, "spot_number": spot_number}
"""Two judges + the matcher -> one decision per activity, with a confidence
the vetting colour will follow: agreed (both judges chose the same programme),
split (they differ; the pick is whichever the matcher also chose, else judge A),
low (either judge said low, or the judges disagree on the OBJECTIVE, in which
case the filed objective wins when one of them kept it)."""
import json, sys, collections
out_file, dump_file, decisions_file = sys.argv[1], sys.argv[2], sys.argv[3]
res = json.load(open(out_file))["result"]
A = {d["id"]: d for d in res["judgeA"]}; B = {d["id"]: d for d in res["judgeB"]}
dump = json.load(open(dump_file))
prg = {int(p["id"]): p for p in dump["programmes"]}; obj = {int(o["id"]): o for o in dump["objectives"]}
rows = {int(r["id"]): r for r in dump["rows"]}
def valid(d): return d and int(d["programme_id"]) in prg and int(prg[int(d["programme_id"])]["objective_id"]) == int(d["objective_id"])
decisions = []; stats = collections.Counter()
for rid, r in sorted(rows.items()):
    a, b = A.get(rid), B.get(rid)
    if not valid(a): a = None
    if not valid(b): b = None
    m = r["proposals"][0] if r["proposals"] else None
    if a and b and int(a["programme_id"]) == int(b["programme_id"]):
        pick, conf = a, ("low" if "low" in (a["confidence"], b["confidence"]) else "agreed")
        reason = a["reason"]
    elif a and b:
        same_obj = int(a["objective_id"]) == int(b["objective_id"])
        if m and int(m["programme_id"]) in (int(a["programme_id"]), int(b["programme_id"])):
            pick = a if int(m["programme_id"]) == int(a["programme_id"]) else b
        elif not same_obj and int(r["objective_id"]) in (int(a["objective_id"]), int(b["objective_id"])):
            pick = a if int(a["objective_id"]) == int(r["objective_id"]) else b   # the filed objective wins a tie
        else:
            pick = a
        conf = "low" if not same_obj else "split"
        other = b if pick is a else a
        reason = f"{pick['reason']} (The other judge preferred {prg[int(other['programme_id'])]['abbr']}: {other['reason']})"
    elif a or b:
        pick, conf, reason = (a or b), "low", (a or b)["reason"] + " (only one judge gave a valid placement)"
    else:
        stats["no_valid_decision"] += 1; continue
    stats[conf] += 1
    decisions.append({"id": rid, "objective_id": int(pick["objective_id"]), "programme_id": int(pick["programme_id"]),
                      "confidence": conf, "reason": reason,
                      "_old": f"{r['abbr']} obj {r['objective_id']} prg {r['programme_id']}",
                      "_new": f"{obj[int(pick['objective_id'])]['abbr']} / {prg[int(pick['programme_id'])]['abbr']}",
                      "_moves_objective": int(pick["objective_id"]) != int(r["objective_id"])})
json.dump(decisions, open(decisions_file, "w"), indent=1, ensure_ascii=False)
print("decisions:", len(decisions), dict(stats), "| objective changes:", sum(d["_moves_objective"] for d in decisions))

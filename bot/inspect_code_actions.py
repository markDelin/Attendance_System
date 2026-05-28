import json
import os

logs_dir = r"C:\Users\MCK\.gemini\antigravity-ide\brain\2ea1f5c1-c54d-47af-b144-45d03ae464f4\.system_generated\logs"
transcript_path = os.path.join(logs_dir, "transcript.jsonl")
scratch_dir = r"C:\Users\MCK\.gemini\antigravity-ide\brain\2ea1f5c1-c54d-47af-b144-45d03ae464f4\scratch"

with open(transcript_path, 'r', encoding='utf-8') as f:
    for i, line in enumerate(f):
        try:
            step = json.loads(line)
            if step.get('type') == 'PLANNER_RESPONSE':
                tool_calls = step.get('tool_calls', [])
                for tc in tool_calls:
                    name = tc.get('name')
                    args = tc.get('args', {})
                    target_file = args.get('TargetFile', '')
                    if 'attendance_bot.py' in target_file:
                        print(f"PLANNER_RESPONSE step index {i}: tool={name}")
                        output_file = os.path.join(scratch_dir, f"step_{i}.json")
                        with open(output_file, 'w', encoding='utf-8') as out_f:
                            json.dump({
                                'step': i,
                                'tool': name,
                                'args': args
                            }, out_f, indent=2, ensure_ascii=False)
                        print(f"  Wrote {output_file}")
        except Exception as e:
            pass

import asyncio
import edge_tts
import json
import sys
import os
import random

VOICE = "km-KH-SreymomNeural"

async def generate_batch(manifest_path: str) -> None:
    with open(manifest_path, "r", encoding="utf-8") as manifest_file:
        jobs = json.load(manifest_file)

    semaphore = asyncio.Semaphore(2)

    async def generate(job: dict) -> None:
        async with semaphore:
            for attempt in range(5):
                try:
                    communicate = edge_tts.Communicate(
                        job["text"], VOICE, rate="+0%", pitch="+0Hz"
                    )
                    await communicate.save(job["output_file"])

                    if not os.path.exists(job["output_file"]) or os.path.getsize(job["output_file"]) == 0:
                        raise RuntimeError(f"TTS did not create audio: {job['output_file']}")
                    return
                except Exception as error:
                    if os.path.exists(job["output_file"]):
                        os.remove(job["output_file"])
                    if attempt == 4:
                        raise RuntimeError(
                            "Edge TTS failed for {} after 5 attempts: {}".format(
                                os.path.basename(job["output_file"]), error
                            )
                        ) from error
                    await asyncio.sleep(min(30, 2 ** attempt) + random.random())

    results = await asyncio.gather(
        *(generate(job) for job in jobs),
        return_exceptions=True
    )
    failures = [str(result) for result in results if isinstance(result, Exception)]
    if failures:
        raise RuntimeError("; ".join(failures[:5]))

async def amain() -> None:
    try:
        if len(sys.argv) == 3 and sys.argv[1] == "--batch":
            await generate_batch(sys.argv[2])
            with open(sys.argv[2], "r", encoding="utf-8") as manifest_file:
                segment_count = len(json.load(manifest_file))
            print("Success: generated {} speech segments".format(segment_count))
            return

        # ឆែកចំនួន Parameter
        if len(sys.argv) < 3:
            print("Error: Missing arguments. Usage: python tts_edge.py <text> <output_file>")
            sys.exit(1)

        TEXT = sys.argv[1]
        OUTPUT_FILE = sys.argv[2]

        # ឆែកថាអត្ថបទមានខ្លឹមសារដែរឬទេ
        if not TEXT.strip():
            print("Error: Text input is empty.")
            sys.exit(1)

        # កំណត់ល្បឿននិយាយ (Rate) និង កម្ពស់សំឡេង (Pitch) បើចង់
        # +0% គឺធម្មតា, -10% គឺយឺតជាងមុនបន្តិចឱ្យងាយស្តាប់
        communicate = edge_tts.Communicate(TEXT, VOICE, rate="+0%", pitch="+0Hz")

        await communicate.save(OUTPUT_FILE)

        # ផ្ទៀងផ្ទាត់ថា File ពិតជាបានបង្កើតមែន
        if os.path.exists(OUTPUT_FILE) and os.path.getsize(OUTPUT_FILE) > 0:
            print(f"Success: {OUTPUT_FILE}")
        else:
            print("Error: File was created but is empty.")
            sys.exit(1)

    except edge_tts.exceptions.NoAudioReceived:
        print("Error: Microsoft Edge TTS returned no audio. Check if text contains invalid characters.")
        sys.exit(1)
    except Exception as e:
        print(f"Python Error: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    # បង្ខំឱ្យប្រើ ProactorEventLoop លើ Windows ដើម្បីស្ថិរភាព
    if sys.platform == 'win32':
        asyncio.set_event_loop_policy(asyncio.WindowsProactorEventLoopPolicy())
    asyncio.run(amain())

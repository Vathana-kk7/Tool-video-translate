import asyncio
import edge_tts
import sys
import os

async def amain() -> None:
    try:
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

        VOICE = "km-KH-SreymomNeural"

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

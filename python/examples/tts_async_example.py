import asyncio
import base64
from aki_io import Aki

def save_audio(audio_base64: str, output_filename: str = "output.wav"):
    audio_data = base64.b64decode(audio_base64)
    with open(output_filename, "wb") as f:
        f.write(audio_data)
    print(f"Saved audio to: {output_filename}")

def progress_callback(progress_info, progress_data):
    if progress_info:
        print(f"Progress: {progress_info}")
    if progress_data:
        print(f"Progress data: {progress_data}")

async def main():
    aki = Aki('xtts', '181e35ac-7b7d-4bfe-9f12-153757ec3952')

    await aki.do_api_login_async()

    params = {
        "text": "This is an asynchronous text to speech example.",
        "language": "en",
        "voice": "emma"
    }
    async def result_callback(result):
        if result and 'audio' in result:
            save_audio(result['audio'], "output_async.wav")
    
    await aki.do_api_request_async(
        params,
        result_callback=result_callback,
        progress_callback=progress_callback
    )

    await aki.close_session()

if __name__ == "__main__":
    asyncio.run(main())
import base64
from aki_io import Aki

def save_audio(audio_base64: str, output_filename: str = "output.wav"):
    audio_data = base64.b64decode(audio_base64)
    with open(output_filename, "wb") as f:
        f.write(audio_data)
    print(f"Saved audio to: {output_filename}")

def progress_callback(progress_info, progress_data):
    if progress_info:
        print(f"Progress: {progress_info}%")
    if progress_data:
        print(f"Progress data: {progress_data}")

def main():
    aki = Aki('xtts', '181e35ac-7b7d-4bfe-9f12-153757ec3952')

    params = {
        "text": "Hello! This is a example of text to speech.",
        "language": "en",
        "voice": "emma", 
    }

    result = aki.do_api_request(
        params,
        progress_callback=progress_callback
    )
    
    if result and 'audio' in result:
        save_audio(result['audio'])

if __name__ == "__main__":
    main()
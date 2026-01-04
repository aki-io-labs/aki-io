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
    aki = Aki('xttsv2', 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37')

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
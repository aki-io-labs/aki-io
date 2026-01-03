import asyncio
import json
from aki_io import Aki

async def main():
    aki = Aki('llama3_8b_chat', '181e35ac-7b7d-4bfe-9f12-153757ec3952')
    
    chat_context = [
        {"role": "user", "content": "Hi! How are you?"},
        {"role": "assistant", "content": "I'm doing well, thank you! How can I help you today?"}
    ]

    params = {
        "prompt_input": "What is the capital of Germany?",
        "chat_context": json.dumps(chat_context),
        "top_k": 40,
        "top_p": 0.9,
        "temperature": 0.8,
        "max_gen_tokens": 1000
    }

    output_generator = aki.get_api_request_generator(params)
    
    try:
        async for progress in output_generator:
            if isinstance(progress, tuple) and len(progress) == 2:
                progress_info, progress_data = progress
                print(f"Progress: {progress_info} - {progress_data}")
            else:
                print(f"Progress: {progress}")
    except Exception as e:
        print(f"Error occurred: {e}")

if __name__ == "__main__":
    asyncio.run(main())
import asyncio
import json
from aki_io import Aki

async def result_callback(result):
    print("Async result callback:", result)

async def progress_callback(progress_info, progress_data):
    print(f"Async progress: {progress_info} - {progress_data}") Example of async operation

async def progress_error_callback(error_description):
    print("Async error:", error_description)

async def main():
    aki = Aki('llama3_8b_chat', '181e35ac-7b7d-4bfe-9f12-153757ec3952')
    await aki.do_api_login_async()

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
        "max_gen_tokens": 100
    }

    result = await aki.do_api_request_async(
        params,
        result_callback,
        progress_callback,
        progress_error_callback
    )

    print("Async with async callbacks result:", result)
    await aki.close_session()

if __name__ == "__main__":
    asyncio.run(main()) 
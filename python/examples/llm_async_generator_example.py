import asyncio
from aki_io import Aki

aki = Aki('llama3_8b_chat', 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37')

chat_context = [
    {"role": "system", "content": "You are a helpful assistant named AKI."},
    {"role": "assistant", "content": "How can I help you today?"},
    {"role": "user", "content": "Tell me a funny store with more than 100 words"},
]

params = {
    "chat_context": chat_context,
    "top_k": 40,
    "top_p": 0.9,
    "temperature": 0.8,
    "max_gen_tokens": 1000
}


async def do_example_request():
    output_generator = aki.get_api_request_generator(params)
    
    try:
        async for result in output_generator:
            if isinstance(result, tuple) and len(result) == 2:
                progress_info, progress_data = result
                print(f"Progress: {progress_info} - {progress_data}")
            else:
                print(f"Result: {result}")
    except Exception as e:
        print(f"Error occurred: {e}")


# use create_task() instead of run() to fire in background
asyncio.run(do_example_request()) 

asyncio.run(aki.close_session())
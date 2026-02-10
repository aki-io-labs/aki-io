const { Aki, doAPIRequest } = require('../aki_io');

const aki = new Aki('llama3_8b_chat', 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37')

const chatContext = [
    {role: 'system', content: 'You are a helpful assistant named AKI.' },
    {role: 'assistant', content: 'How can I help you today?' },
    {role: 'user', content: 'Tell me a funny story with more than 100 words' }
];

const params = {
    chat_context: JSON.stringify(chatContext),
    top_k: 40,
    top_p: 0.9,
    temperature: 0.8,
    max_gen_tokens: 1000
};

output_position = 0

console.log('\n🤖 Assistant: ');

// Make the API request
aki.doAPIRequest(
    params,
    (result) => {
        if (!result || result.success === false) {
            console.error('❌ API Error:', result.error || 'Unknown error');
            return;
        }

        process.stdout.write(result.text.slice(output_position) + '\n');

        console.log('\n📊 Stats:', {
            'Generated Tokens': result.num_generated_tokens,
            'Compute Duration': `${result.compute_duration.toFixed(2)}s`,
            'Total Duration': `${result.total_duration.toFixed(2)}s`
        });

        console.log('\n✨ Chat completed!');
    },
    (progress, progress_data) => {
        if(progress_data) {
            text = progress_data.text
            process.stdout.write(text.slice(output_position));
            output_position = text.length 
        }
    }
);

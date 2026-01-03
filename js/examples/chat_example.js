const { Aki, doAPIRequest } = require('../aki_io');

// Configuration
const CONFIG = {
    ENDPOINT: 'llama3_8b_chat', 
    API_KEY: '181e35ac-7b7d-4bfe-9f12-153757ec3952'
};


// Chat parameters
const chatContext = [
    { role: 'user', content: 'Hi! How are you?' },
    { role: 'assistant', content: 'I\'m doing well, thank you! How can I help you today?' }
];

const params = {
    prompt_input: 'Tell me a joke',
    chat_context: JSON.stringify(chatContext),
    top_k: 40,
    top_p: 0.9,
    temperature: 0.8,
    max_gen_tokens: 1000
};

console.log('💬 Starting chat...');

// Make the API request
doAPIRequest(
    CONFIG.ENDPOINT,
    CONFIG.API_KEY,
    params,
    (result, error) => {
        if (error) {
            console.error('❌ Error:', error.message || 'Unknown error');
            if (error.response) {
                console.error('Response status:', error.response.status);
                console.error('Response data:', JSON.stringify(error.response.data, null, 2));
            }
            return;
        }

        console.log('\n📄 Full API response:', JSON.stringify(result, null, 2));

        if (!result) {
            console.log('⚠️  No response received from the API');
            return;
        }

        // Handle the response
        if (result && result.success === false) {
            console.error('❌ API Error:', result.error || 'Unknown error');
            return;
        }

        if (result && result.text) {
            console.log('\n🤖 Assistant:', result.text);
            console.log('\n📊 Stats:', {
                'Generated Tokens': result.num_generated_tokens,
                'Compute Duration': `${result.compute_duration.toFixed(2)}s`,
                'Total Duration': `${result.total_duration.toFixed(2)}s`
            });
        } else {
            console.log('⚠️  Unexpected response format:');
            console.log(JSON.stringify(result, null, 2));
        }
        
        console.log('\n✨ Chat completed!');
    },
    (progress) => {
        const msg = progress.progress >= 0 
            ? `Progress: ${progress.progress}%` 
            : 'Thinking...';
        process.stdout.write(`\r${msg}${' '.repeat(20)}`);
    }
);

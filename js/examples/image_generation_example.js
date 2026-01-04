const { doAPIRequest } = require('../aki_io');
const fs = require('fs');

// Configuration
const CONFIG = {
    ENDPOINT: 'qwen_image',
    API_KEY: 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37'
};


// Image generation parameters
const params = {
    prompt: 'A beautiful sunset over mountains',
    height: 1024,
    width: 1024,
    steps: 30,
    guidance: 7.5,
    wait_for_result: true
};

console.log('🚀 Starting image generation...');

// Make the API request
doAPIRequest(
    CONFIG.ENDPOINT,
    CONFIG.API_KEY,
    params,
    (result, error) => {
        if (error) {
            console.error('❌ Error:', error.message || 'Unknown error');
            return;
        }

        const images = result.images || [];
        if (images.length === 0) {
            console.log('⚠️  No images were generated');
            return;
        }

        // Save the first image
        const imageData = images[0].includes(',') ? images[0].split(',')[1] : images[0];
        const outputPath = 'generated_image.png';
        fs.writeFileSync(outputPath, Buffer.from(imageData, 'base64'));
        
        console.log(`✅ Image saved as ${outputPath}`);
        console.log('✨ All done!');
    },
    (progress) => {
        const msg = progress.progress >= 0 
            ? `Progress: ${progress.progress}%` 
            : 'Starting...';
        process.stdout.write(`\r${msg}${' '.repeat(20)}`);
    }
);

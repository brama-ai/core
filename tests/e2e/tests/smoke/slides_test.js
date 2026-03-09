// Smoke: Slidev Slides test
const fs = require('fs');
const path = require('path');
const assert = require('assert');

Feature('Smoke: Slides');

// Get all .md files in the slides directory except README.md
const slidesDir = path.join(__dirname, '../../../slides');
const existingSlides = fs.existsSync(slidesDir) 
    ? fs.readdirSync(slidesDir)
        .filter(file => file.endsWith('.md') && file !== 'README.md')
        .map(file => file.replace('.md', ''))
    : ['slides']; // Fallback to 'slides' if dir not found

Scenario('opens and verifies all existing slides @smoke @slides', async ({ I }) => {
    for (const slideName of existingSlides) {
        const url = `/slides/slide/${slideName}/`;
        
        I.say(`Checking slide: ${slideName} at ${url}`);
        I.amOnPage(url);

        // Wait for the app container (Slidev specific)
        I.waitForElement('#app', 10);

        // Verify some specific slidev elements
        I.seeElement('.slidev-layout');
    }
}).tag('@smoke').tag('@slides');

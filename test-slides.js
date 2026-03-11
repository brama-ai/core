const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  const slides = [
    { url: '/slides/slide/main/', title: 'AI Community Platform' },
    { url: '/slides/slide/adr/', title: 'Architecture Decision Records' },
    { url: '/slides/slide/hld/', title: 'HLD' },
  ];

  const errors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') errors.push(msg.text());
  });

  for (const slide of slides) {
    console.log(`\n🌐 Testing: http://localhost${slide.url}`);
    try {
      const response = await page.goto(`http://localhost${slide.url}`, { 
        waitUntil: 'domcontentloaded',
        timeout: 10000 
      });
      console.log(`   Status: ${response?.status()}`);
      
      await page.waitForTimeout(1500);
      
      const title = await page.title();
      console.log(`   Title: ${title}`);
      
      const h1 = await page.$eval('h1', el => el.textContent).catch(() => 'NOT FOUND');
      console.log(`   H1: ${h1}`);
      
      console.log(`   Console errors: ${errors.length}`);
    } catch (e) {
      console.log(`   ERROR: ${e.message}`);
    }
  }

  await browser.close();
})();

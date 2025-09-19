const express = require('express');
const { get_ab } = require('./js/a_bogus');
const { get_sign } = require('./js/sign');

const app = express();
const port = 3000;

// 中间件用于解析JSON请求体
app.use(express.json());

// 提供a_bogus.js的get_ab函数接口
app.post('/get_ab', (req, res) => {
    try {
        const { dpf, ua } = req.body;
        if (!dpf || !ua) {
            return res.status(400).json({ error: 'dpf and ua parameters are required' });
        }
        const result = get_ab(dpf, ua);
        res.json({ a_bogus: result });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 提供sign.js的get_sign函数接口
app.post('/get_sign', (req, res) => {
    try {
        const { params, ua } = req.body;

        if (!params) {
            return res.status(400).json({ error: 'params is required' });
        }

        // ✅ 使用传入的 ua，否则用默认值
        const userAgent = ua || 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

        // 模拟浏览器环境
        global.navigator = { userAgent };
        global.document = {};
        global.window = {};

        // 如果 sign.js 用到其他变量，也 mock（常见于加密逻辑）
        global.chrome = {
            runtime: {},
        };
        global.external = {};
        global.fetch = fetch; // 如果 Node 版本支持，或用 node-fetch/polyfill

        // 注意：有些 sign 函数需要 performance
        global.performance = {
            now: () => Date.now() * 1000, // 或者简单返回时间戳
            timeOrigin: Date.now()
        };

        const result = get_sign(params);
        res.json({ sign: result });

    } catch (error) {
        console.error('Sign error:', error);
        res.status(500).json({ error: error.message });
    }
});

// 启动服务
app.listen(port, () => {
    console.log(`Node.js服务运行在 http://localhost:${port}`);
});

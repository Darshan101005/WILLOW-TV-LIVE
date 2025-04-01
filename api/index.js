import fetch from 'node-fetch';
import { URL } from 'url';

export default async (req, res) => {
  // Extract URL from query parameter or path
  let targetUrl = req.query.url || req.url.replace('/api?url=', '').replace('/api/', '');
  
  // Add https:// if missing
  if (!targetUrl.startsWith('http')) {
    targetUrl = 'https://' + targetUrl;
  }

  // Validate URL
  try {
    new URL(targetUrl);
  } catch (e) {
    return res.status(400).json({ error: 'Invalid URL format' });
  }

  try {
    // Fetch the original content
    const response = await fetch(targetUrl, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36'
      },
      redirect: 'follow'
    });

    if (!response.ok) {
      return res.status(502).json({ error: 'Upstream request failed' });
    }

    const contentType = response.headers.get('content-type') || '';
    let body = await response.text();

    // Process HLS playlists
    if (contentType.includes('application/vnd.apple.mpegurl')) {
      const proxyBase = `https://${req.headers.host}/api/`;
      body = body.split('\n').map(line => {
        if (line.startsWith('http')) {
          return `${proxyBase}${line}`;
        }
        if (line && !line.startsWith('#') && !line.includes('://')) {
          const baseUrl = new URL(targetUrl);
          return `${proxyBase}${baseUrl.origin}/${line}`;
        }
        return line;
      }).join('\n');
    }

    // Set response headers
    res.setHeader('Content-Type', contentType);
    res.setHeader('Access-Control-Allow-Origin', '*');
    
    return res.send(body);

  } catch (error) {
    console.error('Proxy error:', error);
    return res.status(500).json({ error: 'Internal server error' });
  }
};
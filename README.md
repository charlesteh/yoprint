P.S. This project is also live in [https://yoprint.charlesteh.io](https://yoprint.charlesteh.io)!

I use the following to host:

- Laravel Forge
- Laravel Horizon managed by Forge
- Laravel Reverb managed by Forge
- 2 Cloudflare A Records: project and WSS websocket for Reverb
- Hosted in 2 vCPU + 4GB RAM Hetzner VPS, may take 30-40 mins for processing initial records

Key details

- Laravel Horizon for Jobs
- Laravel Reverb to dispatch Update events, handled by Horizon Jobs
- Uses maatwebsite/excel (my favorite laravel CSV package)
- Uses league/fractal for Transformers
- Uses chunking to split CSV files to 5MB per chunk to overcome upload limit (doesn't make sense to increase PHP upload limit at all)

Appreciate the opportunity for a fun and entertaining project!

Charles Teh (charlesteh90@gmail.com)

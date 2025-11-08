Key details

- Laravel Horizon for Jobs
- Laravel Reverb to dispatch Update events, handled by Horizon Jobs
- Uses maatwebsite/excel (my favorite laravel CSV package)
- Uses league/fractal for Transformers
- Uses chunking to split CSV files to 5MB per chunk to overcome upload limit (doesn't make sense to increase PHP upload limit at all)

Appreciate the opportunity for a fun and entertaining project!

Charles Teh (charlesteh90@gmail.com)

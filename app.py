from flask import Flask, render_template, request, send_file, send_from_directory
from flask_compress import Compress
from PIL import Image, ImageDraw, ImageColor
from random import randrange
from datetime import datetime, timedelta
import logging
from logging import StreamHandler
import io

app = Flask(__name__)

# Enable gzip compression
compress = Compress()
compress.init_app(app)

# Configure compression settings
app.config['COMPRESS_MIMETYPES'] = [
    'text/html',
    'text/css',
    'text/xml',
    'text/javascript',
    'application/json',
    'application/javascript',
    'application/xml',
    'application/rss+xml',
    'application/atom+xml',
    'image/svg+xml'
]
app.config['COMPRESS_LEVEL'] = 6  # Compression level (1-9, 6 is good balance)
app.config['COMPRESS_MIN_SIZE'] = 500  # Only compress files larger than 500 bytes

console_handler = StreamHandler()
console_handler.setLevel(logging.DEBUG)
console_handler.setFormatter(logging.Formatter(
    '%(asctime)s %(levelname)s: %(message)s [in %(pathname)s:%(lineno)d]'
))

app.logger.addHandler(console_handler)
app.logger.setLevel(logging.DEBUG)

@app.after_request
def after_request(response):
    """Add security and caching headers to all responses"""
    
    # Security Headers
    response.headers['X-Content-Type-Options'] = 'nosniff'
    response.headers['X-Frame-Options'] = 'DENY'
    response.headers['X-XSS-Protection'] = '1; mode=block'
    response.headers['Referrer-Policy'] = 'strict-origin-when-cross-origin'
    response.headers['Permissions-Policy'] = 'geolocation=(), microphone=(), camera=()'
    
    # Content Security Policy
    csp = (
        "default-src 'self'; "
        "script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://pagead2.googlesyndication.com; "
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        "font-src 'self' https://fonts.gstatic.com; "
        "img-src 'self' data: https:; "
        "connect-src 'self' https://updates.tileimagegen.uk https://www.google-analytics.com; "
        "frame-src https://pagead2.googlesyndication.com; "
        "object-src 'none'; "
        "base-uri 'self'"
    )
    response.headers['Content-Security-Policy'] = csp
    
    # Caching Headers for different file types
    if request.endpoint == 'static':
        # Static files (CSS, JS, images) - cache for 1 year
        expires = datetime.utcnow() + timedelta(days=365)
        response.headers['Expires'] = expires.strftime('%a, %d %b %Y %H:%M:%S GMT')
        response.headers['Cache-Control'] = 'public, max-age=31536000, immutable'
    elif request.endpoint in ['static_from_root']:
        # Robots.txt, sitemap.xml, etc. - cache for 1 day
        expires = datetime.utcnow() + timedelta(days=1)
        response.headers['Expires'] = expires.strftime('%a, %d %b %Y %H:%M:%S GMT')
        response.headers['Cache-Control'] = 'public, max-age=86400'
    elif request.endpoint == 'index':
        # Main page - cache for 1 hour
        expires = datetime.utcnow() + timedelta(hours=1)
        response.headers['Expires'] = expires.strftime('%a, %d %b %Y %H:%M:%S GMT')
        response.headers['Cache-Control'] = 'public, max-age=3600'
    elif request.endpoint == 'generate':
        # Generated images - no cache (always fresh)
        response.headers['Cache-Control'] = 'no-cache, no-store, must-revalidate'
        response.headers['Pragma'] = 'no-cache'
        response.headers['Expires'] = '0'
    
    return response

@app.route('/favicon.ico')
@app.route('/robots.txt')
@app.route('/sitemap.xml')
@app.route('/ads.txt')
def static_from_root():
    return send_from_directory(app.static_folder, request.path[1:])

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/generate', methods=['POST'])
def generate():
    try:
        grout_size = int(request.form.get('groutSize', 0))
        tile_size_width = int(request.form.get('tileWidth', 0))
        tile_size_height = int(request.form.get('tileHeight', 0))
        grout_colour = request.form.get('groutColour', '#000000')
        rgb = ImageColor.getcolor(grout_colour, "RGB")
        tile_name = request.form.get('tileName', 'tile_image_result')
        layout_type = request.form.get('layoutType', 'stacked')

        images = request.files.getlist('images')
        while len(images) < 4:
            images.append(images[0])

        image_objects = [Image.open(img).convert('RGBA') for img in images[:4]]
        tile = [img.resize((tile_size_width, tile_size_height), Image.Resampling.LANCZOS) for img in image_objects]

        width, height = tile[0].size
        ratio = width / height

        if layout_type == 'basketWeave':
            if ratio == 3:
                result = Image.new('RGBA', (width * 2 + grout_size * 4, height * 6 + grout_size), rgb + (255,))
            elif ratio == 2:
                result = Image.new('RGBA', (width*2 + grout_size*3, width*2 + grout_size*3), rgb + (255,))
            elif ratio == 4:
                result = Image.new('RGBA', (width*2 + grout_size*5, width*2 + grout_size*5), rgb + (255,))
            elif ratio == 5:
                result = Image.new('RGBA', (width*2 + grout_size*6, width*2 + grout_size*6), rgb + (255,))
            elif ratio == 6:
                result = Image.new('RGBA', (width*2 + grout_size*7, width*2 + grout_size*7), rgb + (255,))
        elif layout_type == 'herringbone':
            if ratio == 3:
                result = Image.new('RGBA', (width*2 + grout_size*3, width*2 + grout_size*3), rgb + (255,))
            elif ratio == 4:
                result = Image.new('RGBA', (width*2 + grout_size*4, width*2 + grout_size*4), rgb + (255,))
            elif ratio == 5:
                result = Image.new('RGBA', (width*2 + grout_size*5, width*2 + grout_size*5), rgb + (255,))
            elif ratio == 6:
                result = Image.new('RGBA', (width*2 + grout_size*6, width*2 + grout_size*6), rgb + (255,))
            elif ratio == 7:
                result = Image.new('RGBA', (width*2 + grout_size*7, width*2 + grout_size*7), rgb + (255,))
            elif ratio == 8:
                result = Image.new('RGBA', (width*2 + grout_size*8, width*2 + grout_size*8), rgb + (255,))
            elif ratio == 9:
                result = Image.new('RGBA', (width*2 + grout_size*9, width*2 + grout_size*9), rgb + (255,))
            elif ratio == 10:
                result = Image.new('RGBA', (width*2 + grout_size*10, width*2 + grout_size*10), rgb + (255,))
            else:
                result = Image.new('RGBA', (width*2 + grout_size, width*2 + grout_size), rgb + (255,))
        elif layout_type == 'hexagon':
            result = Image.new('RGBA', (width*3 + grout_size*3, height*3 + grout_size*3), rgb + (255,))
        elif layout_type in ['third', 'vertThird']:
            result = Image.new('RGBA', (width + grout_size, height * 3 + grout_size * 3), rgb + (255,))
        else:
            result = Image.new('RGBA', (width*2 + grout_size*2, height*2 + grout_size*2), rgb + (255,))

        draw = ImageDraw.Draw(result)
        vertical_midpoint = (width * 2 + grout_size * 2)
        horizontal_midpoint = (height * 2 + grout_size * 2) if layout_type != 'basketWeave' else (height * 3 + grout_size) if ratio == 3 else (height * 2 + grout_size)

        quarter = width // 4
        half = width // 2
        third = width // 3
        twothird = third * 2
        hexpercent = int(width * 0.26)

        #Paste Images
        if layout_type in ['brickBond', 'vertBrick']:
            result.paste(tile[0], (0 - half, grout_size)) #1L
            result.paste(tile[1], (grout_size + half, grout_size)) #2
            result.paste(tile[0], (grout_size*2 + width + half, grout_size)) #1R
            result.paste(tile[2], (grout_size, grout_size*2 + height)) #3
            result.paste(tile[3], (grout_size*2 + width, grout_size*2 + height)) #4
        elif layout_type == 'herringbone':
            if ratio == 2:
                # Horizontal
                result.paste(tile[0], (grout_size*2 + height, grout_size)) #1
                result.paste(tile[1], (grout_size, grout_size*2 + height)) #2
                result.paste(tile[2], (0 - height, grout_size*3 + height*2)) #3L
                result.paste(tile[2], (grout_size*3 + height*3, grout_size*2 + height*2)) #3R
                result.paste(tile[3], (grout_size*2 + width, grout_size*3 + height*3)) #4

                # Vertical
                result.paste(tile[0].rotate(90, expand=True), (grout_size*3 + height*3, grout_size)) #1
                result.paste(tile[1].rotate(270, expand=True), (grout_size*2 + width, grout_size*2 + height)) #2
                result.paste(tile[2].rotate(90, expand=True), (grout_size*1 + height, grout_size*3 + height*2)) #3
                result.paste(tile[3].rotate(90, expand=True), (grout_size, grout_size*4 + height*3)) #4B
                result.paste(tile[3].rotate(90, expand=True), (grout_size, 0 - height + grout_size)) #4T
            elif ratio == 3:
                # Horizontal
                result.paste(tile[0], (grout_size*2 + height*2, grout_size)) #1
                result.paste(tile[1], (grout_size + height, grout_size*2 + height)) #2
                result.paste(tile[2], (grout_size, grout_size*3 + height*2)) #3
                result.paste(tile[3], (0 - height, grout_size*4 + height*3)) #4L
                result.paste(tile[3], (grout_size*3 + height*5, grout_size*2 + height*3)) #4R
                result.paste(tile[1], (0 - height*2, grout_size*5 + height *4)) #2L
                result.paste(tile[3], (grout_size*2 + height*4, grout_size*3 + height*4)) #2R
                result.paste(tile[2], (grout_size*2 + width, grout_size*4 + height*5)) #3

                # Vertical
                result.paste(tile[0].rotate(90, expand=True), (grout_size*3 + height*5, grout_size)) #1
                result.paste(tile[1].rotate(270, expand=True), (grout_size*2 + height*4, grout_size*2 + height)) #2
                result.paste(tile[2].rotate(90, expand=True), (grout_size*2 + height*3, grout_size*3 + height*2)) #3
                result.paste(tile[3].rotate(270, expand=True), (grout_size + height*2, grout_size*4 + height*3)) #4
                result.paste(tile[3].rotate(90, expand=True), (grout_size + height, 0 - height*2 + grout_size)) #3T
                result.paste(tile[3].rotate(90, expand=True), (grout_size + height, grout_size*5 + height*4)) #3B
                result.paste(tile[1].rotate(90, expand=True), (grout_size, grout_size*2 - height)) #2T
                result.paste(tile[1].rotate(90, expand=True), (0, grout_size*6 + height*5)) #2B
            elif ratio == 4:
                # Horizontal
                result.paste(tile[0], (grout_size*3 + height*3, grout_size)) #1
                result.paste(tile[1], (grout_size*2 + height*2, grout_size*2 + height)) #2
                result.paste(tile[2], (grout_size + height, grout_size*3 + height*2)) #3
                result.paste(tile[3], (grout_size, grout_size*4 + height*3)) #4
                result.paste(tile[0], (0 - height, grout_size*5 + height*4)) #1L
                result.paste(tile[0], (grout_size*4 + height*7, grout_size*2 + height*4)) #1R
                result.paste(tile[1], (0 - height*2, grout_size*6 + height *5)) #2L
                result.paste(tile[1], (grout_size*3 + height*6, grout_size*3 + height*5)) #2R
                result.paste(tile[2], (0 - height*3, grout_size*7 + height*6)) #3L
                result.paste(tile[2], (grout_size*2 + height*5, grout_size*4 + height*6)) #3R
                result.paste(tile[3], (grout_size*3 + width, grout_size*5 + height*7)) #4

                # Vertical
                result.paste(tile[0].rotate(90, expand=True), (grout_size*4 + height*7, grout_size)) #1
                result.paste(tile[1].rotate(270, expand=True), (grout_size*3 + height*6, grout_size*2 + height)) #2
                result.paste(tile[2].rotate(90, expand=True), (grout_size*2 + height*5, grout_size*3 + height*2)) #3
                result.paste(tile[3].rotate(270, expand=True), (grout_size*2 + height*4, grout_size*4 + height*3)) #4
                result.paste(tile[0].rotate(90, expand=True), (grout_size + height*3, grout_size*5 + width)) #1
                result.paste(tile[1].rotate(90, expand=True), (grout_size + height*2, grout_size*6 + height*5)) #2B
                result.paste(tile[1].rotate(90, expand=True), (grout_size*2 + height*2, grout_size - height*3)) #2T
                result.paste(tile[2].rotate(90, expand=True), (grout_size + height, grout_size*7 + height*6)) #3B
                result.paste(tile[2].rotate(90, expand=True), (grout_size + height, grout_size*2 - height*2)) #3T
                result.paste(tile[3].rotate(90, expand=True), (0, grout_size*8 + height*7)) #4B
                result.paste(tile[3].rotate(90, expand=True), (0, grout_size*3 - height)) #4T
            elif ratio == 5:
                # Horizontal
                result.paste(tile[0], (grout_size*4 + height*4, grout_size)) #1
                result.paste(tile[1], (grout_size*3 + height*3, grout_size*2 + height)) #2
                result.paste(tile[2], (grout_size*2 + height*2, grout_size*3 + height*2)) #3
                result.paste(tile[3], (grout_size + height, grout_size*4 + height*3)) #4
                result.paste(tile[0], (grout_size, grout_size*5 + height*4)) #1
                result.paste(tile[1], (0 - height, grout_size*6 + height*5)) #2L
                result.paste(tile[1], (grout_size*5 + height*9, grout_size*2 + height*5)) #2R
                result.paste(tile[2], (0 - height*2, grout_size*7 + height*6)) #3L
                result.paste(tile[2], (grout_size*4 + height*8, grout_size*3 + height*6)) #3R
                result.paste(tile[3], (0 - height*3, grout_size*8 + height*7)) #4L
                result.paste(tile[3], (grout_size*3 + height*7, grout_size*4 + height*7)) #4R
                result.paste(tile[0], (0 - height*4, grout_size*9 + height*8)) #1L
                result.paste(tile[0], (grout_size*2 + height*6, grout_size*5 + height*8)) #1R
                result.paste(tile[1].resize((tile_size_width + grout_size*3, tile_size_height), Image.Resampling.LANCZOS), (grout_size + height*5, grout_size*6 + height*9)) #2

                # Vertical
                result.paste(tile[0].rotate(90, expand=True), (grout_size*5 + height*9, grout_size)) #1
                result.paste(tile[1].rotate(90, expand=True), (grout_size*4 + height*8, grout_size*2 + height)) #2
                result.paste(tile[2].rotate(270, expand=True), (grout_size*3 + height*7, grout_size*3 + height*2)) #3
                result.paste(tile[3].rotate(90, expand=True), (grout_size*2 + height*6, grout_size*4 + height*3)) #4
                result.paste(tile[0].rotate(90, expand=True), (grout_size + height*5, grout_size*5 + height*4)) #1
                result.paste(tile[1].rotate(270, expand=True), (height*4, grout_size*6 + height*5)) #2
                result.paste(tile[2].rotate(90, expand=True), (height*3 - grout_size, grout_size*7 + height*6)) #3B
                result.paste(tile[2].rotate(90, expand=True), (height*3 + grout_size*3, grout_size - height*4)) #3T
                result.paste(tile[3].rotate(90, expand=True), (height*2 - grout_size*2, grout_size*8 + height*7)) #4B
                result.paste(tile[3].rotate(90, expand=True), (height*2 + grout_size*2, grout_size*2 - height*3)) #4T
                result.paste(tile[1].rotate(90, expand=True), (grout_size, grout_size*10 + height*9)) #2B
                result.paste(tile[1].rotate(90, expand=True), (grout_size, grout_size*4 - height)) #2T
                result.paste(tile[0].rotate(270, expand=True), (height - grout_size*3, grout_size*9 + height*8)) #1B
                result.paste(tile[0].rotate(270, expand=True), (height + grout_size*2, grout_size*3 - height*2)) #1T
            elif ratio == 6:
                # Horizontal
                result.paste(tile[0], (grout_size*5 + height*5, grout_size)) #1
                result.paste(tile[1], (grout_size*4 + height*4, grout_size*2 + height)) #2
                result.paste(tile[2], (grout_size*3 + height*3, grout_size*3 + height*2)) #3
                result.paste(tile[3], (grout_size*2 + height*2, grout_size*4 + height*3)) #4
                result.paste(tile[0], (grout_size + height, grout_size*5 + height*4)) #1
                result.paste(tile[1], (grout_size, grout_size*6 + height*5)) #2
                result.paste(tile[2], (grout_size - height, grout_size*7 + height*6)) #3L
                result.paste(tile[2], (grout_size*6 + height*11, grout_size*2 + height*6)) #3R
                result.paste(tile[3], (grout_size - height*2, grout_size*8 + height*7)) #4L
                result.paste(tile[3], (grout_size*5 + height*10, grout_size*3 + height*7)) #4R
                result.paste(tile[0], (grout_size - height*3, grout_size*9 + height*8)) #1L
                result.paste(tile[0], (grout_size*4 + height*9, grout_size*4 + height*8)) #1R
                result.paste(tile[1], (grout_size - height*4, grout_size*10 + height*9)) #2L
                result.paste(tile[1], (grout_size*3 + height*8, grout_size*5 + height*9)) #2R
                result.paste(tile[2], (grout_size - height*5, grout_size*11 + height*10)) #3L
                result.paste(tile[2], (grout_size*2 + height*7, grout_size*6 + height*10)) #3R
                result.paste(tile[3].resize((tile_size_width + grout_size*4, tile_size_height), Image.Resampling.LANCZOS), (grout_size + height*6, grout_size*7 + height*11)) #4
                
                # Vertical
                result.paste(tile[0].rotate(90, expand=True), (grout_size*6 + height*11, grout_size)) #1
                result.paste(tile[1].rotate(90, expand=True), (grout_size*5 + height*10, grout_size*2 + height)) #2
                result.paste(tile[2].rotate(270, expand=True), (grout_size*4 + height*9, grout_size*3 + height*2)) #3
                result.paste(tile[3].rotate(90, expand=True), (grout_size*3 + height*8, grout_size*4 + height*3)) #4
                result.paste(tile[0].rotate(90, expand=True), (grout_size*2 + height*7, grout_size*5 + height*4)) #1
                result.paste(tile[1].rotate(90, expand=True), (grout_size + height*6, grout_size*6 + height*5)) #2
                result.paste(tile[2].rotate(270, expand=True), (height*5, grout_size*7 + height*6)) #3
                result.paste(tile[3].rotate(90, expand=True), (height*4 - grout_size, grout_size*8 + height*7)) #4B
                result.paste(tile[3].rotate(90, expand=True), (height*4 + grout_size*4, grout_size - height*5)) #4T
                result.paste(tile[0].rotate(90, expand=True), (height*3 - grout_size*2, grout_size*9 + height*8)) #1B
                result.paste(tile[0].rotate(90, expand=True), (height*3 + grout_size*3, grout_size*2 - height*4)) #1T
                result.paste(tile[1].rotate(270, expand=True), (height*2 - grout_size*3, grout_size*10 + height*9)) #2B
                result.paste(tile[1].rotate(270, expand=True), (height*2 + grout_size*2, grout_size*3 - height*3)) #2T
                result.paste(tile[3].rotate(90, expand=True), (grout_size, grout_size*12 + height*11)) #4B
                result.paste(tile[3].rotate(90, expand=True), (grout_size, grout_size*5 - height)) #4T
                result.paste(tile[2].rotate(90, expand=True), (height - grout_size*4, grout_size*11 + height*10)) #3B
                result.paste(tile[2].rotate(90, expand=True), (height + grout_size, grout_size*4 - height*2)) #3T
            elif ratio == 7:
                # Horizontal
                result.paste(tile[0], (grout_size*6 + height*6, grout_size)) #1
                result.paste(tile[1], (grout_size*5 + height*5, grout_size*2 + height)) #2
                result.paste(tile[2], (grout_size*4 + height*4, grout_size*3 + height*2)) #3
                result.paste(tile[3], (grout_size*3 + height*3, grout_size*4 + height*3)) #4
                result.paste(tile[0], (grout_size*2 + height*2, grout_size*5 + height*4)) #5
                result.paste(tile[1], (grout_size + height, grout_size*6 + height*5)) #6
                result.paste(tile[2], (grout_size, grout_size*7 + height*6)) #7
                result.paste(tile[3], (grout_size - height, grout_size*8 + height*7)) #8L
                result.paste(tile[3], (grout_size*7 + height*13, grout_size*2 + height*7)) #8R
                result.paste(tile[0], (grout_size - height*2, grout_size*9 + height*8)) #9L
                result.paste(tile[0], (grout_size*6 + height*12, grout_size*3 + height*8)) #9R
                result.paste(tile[1], (grout_size - height*3, grout_size*10 + height*9)) #10L
                result.paste(tile[1], (grout_size*5 + height*11, grout_size*4 + height*9)) #10R
                result.paste(tile[2], (grout_size - height*4, grout_size*11 + height*10)) #11L
                result.paste(tile[2], (grout_size*4 + height*10, grout_size*5 + height*10)) #11R
                result.paste(tile[3], (grout_size - height*5, grout_size*12 + height*11)) #12L
                result.paste(tile[3], (grout_size*3 + height*9, grout_size*6 + height*11)) #12R
                result.paste(tile[0], (grout_size - height*6, grout_size*13 + height*12)) #13L
                result.paste(tile[0], (grout_size*2 + height*8, grout_size*7 + height*12)) #13R
                result.paste(tile[1].resize((tile_size_width + grout_size*5, tile_size_height), Image.Resampling.LANCZOS), (grout_size + height*7, grout_size*8 + height*13)) #14

                # Vertical
                result.paste(tile[0].rotate(90, expand=True), (grout_size*7 + height*13, grout_size)) #1
                result.paste(tile[1].rotate(90, expand=True), (grout_size*6 + height*12, grout_size*2 + height)) #2
                result.paste(tile[2].rotate(270, expand=True), (grout_size*5 + height*11, grout_size*3 + height*2)) #3
                result.paste(tile[3].rotate(90, expand=True), (grout_size*4 + height*10, grout_size*4 + height*3)) #4
                result.paste(tile[0].rotate(90, expand=True), (grout_size*3 + height*9, grout_size*5 + height*4)) #5
                result.paste(tile[1].rotate(90, expand=True), (grout_size*2 + height*8, grout_size*6 + height*5)) #6
                result.paste(tile[2].rotate(270, expand=True), (grout_size + height*7, grout_size*7 + height*6)) #7
                result.paste(tile[3].rotate(90, expand=True), (height*6, grout_size*8 + height*7)) #8
                result.paste(tile[0].rotate(90, expand=True), (height*5 - grout_size, grout_size*9 + height*8)) #9B
                result.paste(tile[0].rotate(90, expand=True), (height*5 + grout_size*5, grout_size - height*6)) #9T
                result.paste(tile[1].rotate(90, expand=True), (height*4 - grout_size*2, grout_size*10 + height*9)) #10B
                result.paste(tile[1].rotate(90, expand=True), (height*4 + grout_size*4, grout_size*2 - height*5)) #10T
                result.paste(tile[2].rotate(270, expand=True), (height*3 - grout_size*3, grout_size*11 + height*10)) #11B
                result.paste(tile[2].rotate(270, expand=True), (height*3 + grout_size*3, grout_size*3 - height*4)) #11T
                result.paste(tile[3].rotate(90, expand=True), (height*2 - grout_size*4, grout_size*12 + height*11)) #12B
                result.paste(tile[3].rotate(90, expand=True), (height*2 + grout_size*2, grout_size*4 - height*3)) #12T
                result.paste(tile[0].rotate(90, expand=True), (height - grout_size*5, grout_size*13 + height*12)) #13B
                result.paste(tile[0].rotate(90, expand=True), (height + grout_size, grout_size*5 - height*2)) #13T
                result.paste(tile[1].rotate(90, expand=True), (grout_size, grout_size*14 + height*13)) #14B
                result.paste(tile[1].rotate(90, expand=True), (grout_size, grout_size*6 - height)) #14T
            elif ratio == 8:
                # Horizontal
                result.paste(tile[0], (grout_size*7 + height*7, grout_size)) #1
                result.paste(tile[1], (grout_size*6 + height*6, grout_size*2 + height)) #2
                result.paste(tile[2], (grout_size*5 + height*5, grout_size*3 + height*2)) #3
                result.paste(tile[3], (grout_size*4 + height*4, grout_size*4 + height*3)) #4
                result.paste(tile[0], (grout_size*3 + height*3, grout_size*5 + height*4)) #5
                result.paste(tile[1], (grout_size*2 + height*2, grout_size*6 + height*5)) #6
                result.paste(tile[2], (grout_size + height, grout_size*7 + height*6)) #7
                result.paste(tile[3], (grout_size, grout_size*8 + height*7)) #8
                result.paste(tile[0], (grout_size - height, grout_size*9 + height*8)) #9L
                result.paste(tile[0], (grout_size*8 + height*15, grout_size*2 + height*8)) #9R
                result.paste(tile[1], (grout_size - height*2, grout_size*10 + height*9)) #10L
                result.paste(tile[1], (grout_size*7 + height*14, grout_size*3 + height*9)) #10R
                result.paste(tile[2], (grout_size - height*3, grout_size*11 + height*10)) #11L
                result.paste(tile[2], (grout_size*6 + height*13, grout_size*4 + height*10)) #11R
                result.paste(tile[3], (grout_size - height*4, grout_size*12 + height*11)) #12L
                result.paste(tile[3], (grout_size*5 + height*12, grout_size*5 + height*11)) #12R
                result.paste(tile[0], (grout_size - height*5, grout_size*13 + height*12)) #13L
                result.paste(tile[0], (grout_size*4 + height*11, grout_size*6 + height*12)) #13R
                result.paste(tile[1], (grout_size - height*6, grout_size*14 + height*13)) #14L
                result.paste(tile[1], (grout_size*3 + height*10, grout_size*7 + height*13)) #14R
                result.paste(tile[2], (grout_size - height*7, grout_size*15 + height*14)) #15L
                result.paste(tile[2], (grout_size*2 + height*9, grout_size*8 + height*14)) #15R
                result.paste(tile[3].resize((tile_size_width + grout_size*6, tile_size_height), Image.Resampling.LANCZOS), (grout_size + height*8, grout_size*9 + height*15)) #16

                # Vertical
                result.paste(tile[0].rotate(90, expand=True), (grout_size*8 + height*15, grout_size)) #1
                result.paste(tile[1].rotate(90, expand=True), (grout_size*7 + height*14, grout_size*2 + height)) #2
                result.paste(tile[2].rotate(270, expand=True), (grout_size*6 + height*13, grout_size*3 + height*2)) #3
                result.paste(tile[3].rotate(90, expand=True), (grout_size*5 + height*12, grout_size*4 + height*3)) #4
                result.paste(tile[0].rotate(90, expand=True), (grout_size*4 + height*11, grout_size*5 + height*4)) #5
                result.paste(tile[1].rotate(90, expand=True), (grout_size*3 + height*10, grout_size*6 + height*5)) #6
                result.paste(tile[2].rotate(270, expand=True), (grout_size*2 + height*9, grout_size*7 + height*6)) #7
                result.paste(tile[3].rotate(90, expand=True), (grout_size + height*8, grout_size*8 + height*7)) #8
                result.paste(tile[0].rotate(90, expand=True), (height*7, grout_size*9 + height*8)) #9
                result.paste(tile[1].rotate(90, expand=True), (height*6 - grout_size, grout_size*10 + height*9)) #10B
                result.paste(tile[1].rotate(90, expand=True), (height*6 + grout_size*6, grout_size - height*7)) #10T
                result.paste(tile[2].rotate(270, expand=True), (height*5 - grout_size*2, grout_size*11 + height*10)) #11B
                result.paste(tile[2].rotate(270, expand=True), (height*5 + grout_size*5, grout_size*2 - height*6)) #11T
                result.paste(tile[3].rotate(90, expand=True), (height*4 - grout_size*3, grout_size*12 + height*11)) #12B
                result.paste(tile[3].rotate(90, expand=True), (height*4 + grout_size*4, grout_size*3 - height*5)) #12T
                result.paste(tile[0].rotate(90, expand=True), (height*3 - grout_size*4, grout_size*13 + height*12)) #13B
                result.paste(tile[0].rotate(90, expand=True), (height*3 + grout_size*3, grout_size*4 - height*4)) #13T
                result.paste(tile[1].rotate(90, expand=True), (height*2 - grout_size*5, grout_size*14 + height*13)) #14B
                result.paste(tile[1].rotate(90, expand=True), (height*2 + grout_size*2, grout_size*5 - height*3)) #14T
                result.paste(tile[2].rotate(270, expand=True), (height - grout_size*6, grout_size*15 + height*14)) #15B
                result.paste(tile[2].rotate(270, expand=True), (height + grout_size, grout_size*6 - height*2)) #15T
                result.paste(tile[3].rotate(90, expand=True), (grout_size, grout_size*16 + height*15)) #16B
                result.paste(tile[3].rotate(90, expand=True), (grout_size, grout_size*7 - height)) #16T

            elif ratio == 9:
                # Horizontal
                result.paste(tile[0], (grout_size*8 + height*8, grout_size)) #1
                result.paste(tile[1], (grout_size*7 + height*7, grout_size*2 + height)) #2
                result.paste(tile[2], (grout_size*6 + height*6, grout_size*3 + height*2)) #3
                result.paste(tile[3], (grout_size*5 + height*5, grout_size*4 + height*3)) #4
                result.paste(tile[0], (grout_size*4 + height*4, grout_size*5 + height*4)) #5
                result.paste(tile[1], (grout_size*3 + height*3, grout_size*6 + height*5)) #6
                result.paste(tile[2], (grout_size*2 + height*2, grout_size*7 + height*6)) #7
                result.paste(tile[3], (grout_size + height, grout_size*8 + height*7)) #8
                result.paste(tile[0], (grout_size, grout_size*9 + height*8)) #9
                result.paste(tile[1], (grout_size - height, grout_size*10 + height*9)) #10L
                result.paste(tile[1], (grout_size*9 + height*17, grout_size*2 + height*9)) #10R
                result.paste(tile[2], (grout_size - height*2, grout_size*11 + height*10)) #11L
                result.paste(tile[2], (grout_size*8 + height*16, grout_size*3 + height*10)) #11R
                result.paste(tile[3], (grout_size - height*3, grout_size*12 + height*11)) #12L
                result.paste(tile[3], (grout_size*7 + height*15, grout_size*4 + height*11)) #12R
                result.paste(tile[0], (grout_size - height*4, grout_size*13 + height*12)) #13L
                result.paste(tile[0], (grout_size*6 + height*14, grout_size*5 + height*12)) #13R
                result.paste(tile[1], (grout_size - height*5, grout_size*14 + height*13)) #14L
                result.paste(tile[1], (grout_size*5 + height*13, grout_size*6 + height*13)) #14R
                result.paste(tile[2], (grout_size - height*6, grout_size*15 + height*14)) #15L
                result.paste(tile[2], (grout_size*4 + height*12, grout_size*7 + height*14)) #15R
                result.paste(tile[3], (grout_size - height*7, grout_size*16 + height*15)) #16L
                result.paste(tile[3], (grout_size*3 + height*11, grout_size*8 + height*15)) #16R
                result.paste(tile[0], (grout_size - height*8, grout_size*17 + height*16)) #17L
                result.paste(tile[0], (grout_size*2 + height*10, grout_size*9 + height*16)) #17R
                result.paste(tile[1].resize((tile_size_width + grout_size*7, tile_size_height), Image.Resampling.LANCZOS), (grout_size + height*9, grout_size*10 + height*17)) #18

                # Vertical
                result.paste(tile[0].rotate(90, expand=True), (grout_size*9 + height*17, grout_size)) #1
                result.paste(tile[1].rotate(90, expand=True), (grout_size*8 + height*16, grout_size*2 + height)) #2
                result.paste(tile[2].rotate(270, expand=True), (grout_size*7 + height*15, grout_size*3 + height*2)) #3
                result.paste(tile[3].rotate(90, expand=True), (grout_size*6 + height*14, grout_size*4 + height*3)) #4
                result.paste(tile[0].rotate(90, expand=True), (grout_size*5 + height*13, grout_size*5 + height*4)) #5
                result.paste(tile[1].rotate(90, expand=True), (grout_size*4 + height*12, grout_size*6 + height*5)) #6
                result.paste(tile[2].rotate(270, expand=True), (grout_size*3 + height*11, grout_size*7 + height*6)) #7
                result.paste(tile[3].rotate(90, expand=True), (grout_size*2 + height*10, grout_size*8 + height*7)) #8
                result.paste(tile[0].rotate(90, expand=True), (grout_size + height*9, grout_size*9 + height*8)) #9
                result.paste(tile[1].rotate(90, expand=True), (height*8, grout_size*10 + height*9)) #10
                result.paste(tile[2].rotate(270, expand=True), (height*7 - grout_size, grout_size*11 + height*10)) #11B
                result.paste(tile[2].rotate(270, expand=True), (height*7 + grout_size*7, grout_size - height*8)) #11T
                result.paste(tile[3].rotate(90, expand=True), (height*6 - grout_size*2, grout_size*12 + height*11)) #12B
                result.paste(tile[3].rotate(90, expand=True), (height*6 + grout_size*6, grout_size*2 - height*7)) #12T
                result.paste(tile[0].rotate(90, expand=True), (height*5 - grout_size*3, grout_size*13 + height*12)) #13B
                result.paste(tile[0].rotate(90, expand=True), (height*5 + grout_size*5, grout_size*3 - height*6)) #13T
                result.paste(tile[1].rotate(90, expand=True), (height*4 - grout_size*4, grout_size*14 + height*13)) #14B
                result.paste(tile[1].rotate(90, expand=True), (height*4 + grout_size*4, grout_size*4 - height*5)) #14T
                result.paste(tile[2].rotate(270, expand=True), (height*3 - grout_size*5, grout_size*15 + height*14)) #15B
                result.paste(tile[2].rotate(270, expand=True), (height*3 + grout_size*3, grout_size*5 - height*4)) #15T
                result.paste(tile[3].rotate(90, expand=True), (height*2 - grout_size*6, grout_size*16 + height*15)) #16B
                result.paste(tile[3].rotate(90, expand=True), (height*2 + grout_size*2, grout_size*6 - height*3)) #16T
                result.paste(tile[0].rotate(90, expand=True), (height - grout_size*7, grout_size*17 + height*16)) #17B
                result.paste(tile[0].rotate(90, expand=True), (height + grout_size, grout_size*7 - height*2)) #17T
                result.paste(tile[1].rotate(90, expand=True), (grout_size, grout_size*18 + height*17)) #18B
                result.paste(tile[1].rotate(90, expand=True), (grout_size, grout_size*8 - height)) #18T

            elif ratio == 10:
                # Horizontal
                result.paste(tile[0], (grout_size*9 + height*9, grout_size)) #1
                result.paste(tile[1], (grout_size*8 + height*8, grout_size*2 + height)) #2
                result.paste(tile[2], (grout_size*7 + height*7, grout_size*3 + height*2)) #3
                result.paste(tile[3], (grout_size*6 + height*6, grout_size*4 + height*3)) #4
                result.paste(tile[0], (grout_size*5 + height*5, grout_size*5 + height*4)) #5
                result.paste(tile[1], (grout_size*4 + height*4, grout_size*6 + height*5)) #6
                result.paste(tile[2], (grout_size*3 + height*3, grout_size*7 + height*6)) #7
                result.paste(tile[3], (grout_size*2 + height*2, grout_size*8 + height*7)) #8
                result.paste(tile[0], (grout_size + height, grout_size*9 + height*8)) #9
                result.paste(tile[1], (0, grout_size*10 + height*9)) #10
                result.paste(tile[2], (0 - grout_size - height, grout_size*11 + height*10)) #11L
                result.paste(tile[2], (grout_size*10 + height*19, grout_size*2 + height*10)) #11R
                result.paste(tile[3], (0 - grout_size*2 - height*2, grout_size*12 + height*11)) #12L
                result.paste(tile[3], (grout_size*9 + height*18, grout_size*3 + height*11)) #12R
                result.paste(tile[0], (0 - grout_size*3 - height*3, grout_size*13 + height*12)) #13L
                result.paste(tile[0], (grout_size*8 + height*17, grout_size*4 + height*12)) #13R
                result.paste(tile[1], (0 - grout_size*4 - height*4, grout_size*14 + height*13)) #14L
                result.paste(tile[1], (grout_size*7 + height*16, grout_size*5 + height*13)) #14R
                result.paste(tile[2], (0 - grout_size*5 - height*5, grout_size*15 + height*14)) #15L
                result.paste(tile[2], (grout_size*6 + height*15, grout_size*6 + height*14)) #15R
                result.paste(tile[3], (0 - grout_size*6 - height*6, grout_size*16 + height*15)) #16L
                result.paste(tile[3], (grout_size*5 + height*14, grout_size*7 + height*15)) #16R
                result.paste(tile[0], (0 - grout_size*7 - height*7, grout_size*17 + height*16)) #17L
                result.paste(tile[0], (grout_size*4 + height*13, grout_size*8 + height*16)) #17R
                result.paste(tile[1], (0 - grout_size*8 - height*8, grout_size*18 + height*17)) #18L
                result.paste(tile[1], (grout_size*3 + height*12, grout_size*9 + height*17)) #18R
                result.paste(tile[2], (0 - grout_size*9 - height*9, grout_size*19 + height*18)) #19L
                result.paste(tile[2], (grout_size*2 + height*11, grout_size*10 + height*18)) #19R
                result.paste(tile[3].resize((tile_size_width + grout_size*8, tile_size_height), Image.Resampling.LANCZOS), (grout_size + height*10, grout_size*11 + height*19)) #20

                # Vertical
                result.paste(tile[0].rotate(90, expand=True), (grout_size*10 + height*19, grout_size)) #1
                result.paste(tile[1].rotate(90, expand=True), (grout_size*9 + height*18, grout_size*2 + height)) #2
                result.paste(tile[2].rotate(270, expand=True), (grout_size*8 + height*17, grout_size*3 + height*2)) #3
                result.paste(tile[3].rotate(90, expand=True), (grout_size*7 + height*16, grout_size*4 + height*3)) #4
                result.paste(tile[0].rotate(90, expand=True), (grout_size*6 + height*15, grout_size*5 + height*4)) #5
                result.paste(tile[1].rotate(90, expand=True), (grout_size*5 + height*14, grout_size*6 + height*5)) #6
                result.paste(tile[2].rotate(270, expand=True), (grout_size*4 + height*13, grout_size*7 + height*6)) #7
                result.paste(tile[3].rotate(90, expand=True), (grout_size*3 + height*12, grout_size*8 + height*7)) #8
                result.paste(tile[0].rotate(90, expand=True), (grout_size*2 + height*11, grout_size*9 + height*8)) #9
                result.paste(tile[1].rotate(90, expand=True), (grout_size + height*10, grout_size*10 + height*9)) #10
                result.paste(tile[2].rotate(270, expand=True), (height*9, grout_size*11 + height*10)) #11
                result.paste(tile[3].rotate(90, expand=True), (height*8 - grout_size, grout_size*12 + height*11)) #12B
                result.paste(tile[3].rotate(90, expand=True), (height*8 + grout_size*8, grout_size - height*9)) #12T
                result.paste(tile[0].rotate(90, expand=True), (height*7 - grout_size*2, grout_size*13 + height*12)) #13B
                result.paste(tile[0].rotate(90, expand=True), (height*7 + grout_size*7, grout_size*2 - height*8)) #13T
                result.paste(tile[1].rotate(90, expand=True), (height*6 - grout_size*3, grout_size*14 + height*13)) #14B
                result.paste(tile[1].rotate(90, expand=True), (height*6 + grout_size*6, grout_size*3 - height*7)) #14T
                result.paste(tile[2].rotate(270, expand=True), (height*5 - grout_size*4, grout_size*15 + height*14)) #15B
                result.paste(tile[2].rotate(270, expand=True), (height*5 + grout_size*5, grout_size*4 - height*6)) #15T
                result.paste(tile[3].rotate(90, expand=True), (height*4 - grout_size*5, grout_size*16 + height*15)) #16B
                result.paste(tile[3].rotate(90, expand=True), (height*4 + grout_size*4, grout_size*5 - height*5)) #16T
                result.paste(tile[0].rotate(90, expand=True), (height*3 - grout_size*6, grout_size*17 + height*16)) #17B
                result.paste(tile[0].rotate(90, expand=True), (height*3 + grout_size*3, grout_size*6 - height*4)) #17T
                result.paste(tile[1].rotate(90, expand=True), (height*2 - grout_size*7, grout_size*18 + height*17)) #18B
                result.paste(tile[1].rotate(90, expand=True), (height*2 + grout_size*2, grout_size*7 - height*3)) #18T
                result.paste(tile[2].rotate(270, expand=True), (height - grout_size*8, grout_size*19 + height*18)) #19B
                result.paste(tile[2].rotate(270, expand=True), (height + grout_size, grout_size*8 - height*2)) #19T
                result.paste(tile[3].rotate(90, expand=True), (grout_size, grout_size*20 + height*19)) #20B
                result.paste(tile[3].rotate(90, expand=True), (grout_size, grout_size*9 - height)) #20T
        elif layout_type == 'basketWeave':
            if ratio == 2:
                result.paste(tile[0].rotate(90, expand=True), (grout_size, grout_size)) #1
                result.paste(tile[1].rotate(90, expand=True), (grout_size + height + grout_size, grout_size)) #2
                result.paste(tile[2], (grout_size + height + grout_size + height + grout_size, grout_size)) #3
                result.paste(tile[3], (grout_size + height + grout_size + height + grout_size, grout_size + height + grout_size)) #4
                result.paste(tile[3], (grout_size, grout_size + width + grout_size)) #5
                result.paste(tile[2], (grout_size, grout_size + width + grout_size + height + grout_size)) #6
                result.paste(tile[1].rotate(90, expand=True), (grout_size + height + grout_size + height, grout_size + height + grout_size + height + grout_size)) #7
                result.paste(tile[0].rotate(90, expand=True), (grout_size + height + grout_size + height + grout_size + height, grout_size + height + grout_size + height + grout_size)) #8
            elif ratio == 3:
                result.paste(tile[0].rotate(90, expand=True), (grout_size, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (height + grout_size*2, grout_size))
                result.paste(tile[2].rotate(90, expand=True), (height*2 + grout_size*3, grout_size))
                result.paste(tile[3], (height*3 + grout_size*4, grout_size))
                result.paste(tile[0], (height*3 + grout_size*4, height + grout_size*2))
                result.paste(tile[1], (height*3 + grout_size*4, height*2 + grout_size*3))
                result.paste(tile[2], (grout_size, height*3 + grout_size*2))
                result.paste(tile[3], (grout_size, height*4 + grout_size*3))
                result.paste(tile[0], (grout_size, height*5 + grout_size*4))
                result.paste(tile[2].rotate(90, expand=True), (height*3 + grout_size*2, height*3 + grout_size*4))
                result.paste(tile[3].rotate(90, expand=True), (height*4 + grout_size*3, height*3 + grout_size*4))
                result.paste(tile[1].rotate(90, expand=True), (height*5 + grout_size*4, height*3 + grout_size*4))
            elif ratio == 4:
                result.paste(tile[0].rotate(90, expand=True), (grout_size, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*2 + height, grout_size))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*3 + height*2, grout_size))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*4 + height*3, grout_size))
                result.paste(tile[0], (grout_size*5 + height*4, grout_size))
                result.paste(tile[1], (grout_size*5 + height*4, grout_size*2 + height))
                result.paste(tile[2], (grout_size*5 + height*4, grout_size*3 + height*2))
                result.paste(tile[3], (grout_size*5 + height*4, grout_size*4 + height*3))
                result.paste(tile[0], (grout_size, grout_size*2 + width))
                result.paste(tile[1], (grout_size, grout_size*3 + width + height))
                result.paste(tile[2], (grout_size, grout_size*4 + width + height*2))
                result.paste(tile[3], (grout_size, grout_size*5 + width + height*3))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*2 + height*4, grout_size*5 + height*4))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*3 + height*5, grout_size*5 + height*4))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*4 + height*6, grout_size*5 + height*4))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*5 + height*7, grout_size*5 + height*4))
            elif ratio == 5:
                result.paste(tile[0].rotate(90, expand=True), (grout_size, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*2 + height, grout_size))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*3 + height*2, grout_size))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*4 + height*3, grout_size))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*5 + height*4, grout_size))
                result.paste(tile[0], (grout_size*6 + height*5, grout_size))
                result.paste(tile[1], (grout_size*6 + height*5, grout_size*2 + height))
                result.paste(tile[2], (grout_size*6 + height*5, grout_size*3 + height*2))
                result.paste(tile[3], (grout_size*6 + height*5, grout_size*4 + height*3))
                result.paste(tile[0], (grout_size*6 + height*5, grout_size*5 + height*4))
                result.paste(tile[0], (grout_size, grout_size*2 + width))
                result.paste(tile[1], (grout_size, grout_size*3 + width + height))
                result.paste(tile[2], (grout_size, grout_size*4 + width + height*2))
                result.paste(tile[3], (grout_size, grout_size*5 + width + height*3))
                result.paste(tile[0], (grout_size, grout_size*6 + width + height*4))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*2 + height*5, grout_size*6 + height*5))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*3 + height*6, grout_size*6 + height*5))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*4 + height*7, grout_size*6 + height*5))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*5 + height*8, grout_size*6 + height*5))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*6 + height*9, grout_size*6 + height*5))
            elif ratio == 6:
                result.paste(tile[0].rotate(90, expand=True), (grout_size, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*2 + height, grout_size))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*3 + height*2, grout_size))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*4 + height*3, grout_size))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*5 + height*4, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*6 + height*5, grout_size))
                result.paste(tile[0], (grout_size*7 + height*6, grout_size))
                result.paste(tile[1], (grout_size*7 + height*6, grout_size*2 + height))
                result.paste(tile[2], (grout_size*7 + height*6, grout_size*3 + height*2))
                result.paste(tile[3], (grout_size*7 + height*6, grout_size*4 + height*3))
                result.paste(tile[0], (grout_size*7 + height*6, grout_size*5 + height*4))
                result.paste(tile[1], (grout_size*7 + height*6, grout_size*6 + height*5))
                result.paste(tile[0], (grout_size, grout_size*2 + width))
                result.paste(tile[1], (grout_size, grout_size*3 + width + height))
                result.paste(tile[2], (grout_size, grout_size*4 + width + height*2))
                result.paste(tile[3], (grout_size, grout_size*5 + width + height*3))
                result.paste(tile[0], (grout_size, grout_size*6 + width + height*4))
                result.paste(tile[1], (grout_size, grout_size*7 + width + height*5))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*2 + height*6, grout_size*7 + height*6))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*3 + height*7, grout_size*7 + height*6))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*4 + height*8, grout_size*7 + height*6))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*5 + height*9, grout_size*7 + height*6))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*6 + height*10, grout_size*7 + height*6))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*7 + height*11, grout_size*7 + height*6))
            elif ratio == 7:
                # First row - vertical tiles
                result.paste(tile[0].rotate(90, expand=True), (grout_size, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*2 + height, grout_size))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*3 + height*2, grout_size))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*4 + height*3, grout_size))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*5 + height*4, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*6 + height*5, grout_size))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*7 + height*6, grout_size))
                
                # Right side - vertical stack
                result.paste(tile[0], (grout_size*8 + height*7, grout_size))
                result.paste(tile[1], (grout_size*8 + height*7, grout_size*2 + height))
                result.paste(tile[2], (grout_size*8 + height*7, grout_size*3 + height*2))
                result.paste(tile[3], (grout_size*8 + height*7, grout_size*4 + height*3))
                result.paste(tile[0], (grout_size*8 + height*7, grout_size*5 + height*4))
                result.paste(tile[1], (grout_size*8 + height*7, grout_size*6 + height*5))
                result.paste(tile[2], (grout_size*8 + height*7, grout_size*7 + height*6))
                
                # Left side - vertical stack
                result.paste(tile[0], (grout_size, grout_size*2 + width))
                result.paste(tile[1], (grout_size, grout_size*3 + width + height))
                result.paste(tile[2], (grout_size, grout_size*4 + width + height*2))
                result.paste(tile[3], (grout_size, grout_size*5 + width + height*3))
                result.paste(tile[0], (grout_size, grout_size*6 + width + height*4))
                result.paste(tile[1], (grout_size, grout_size*7 + width + height*5))
                result.paste(tile[2], (grout_size, grout_size*8 + width + height*6))
                
                # Bottom row - horizontal tiles
                result.paste(tile[0].rotate(90, expand=True), (grout_size*2 + height*7, grout_size*8 + height*7))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*3 + height*8, grout_size*8 + height*7))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*4 + height*9, grout_size*8 + height*7))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*5 + height*10, grout_size*8 + height*7))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*6 + height*11, grout_size*8 + height*7))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*7 + height*12, grout_size*8 + height*7))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*8 + height*13, grout_size*8 + height*7))
            
            elif ratio == 8:
                # First row - vertical tiles
                result.paste(tile[0].rotate(90, expand=True), (grout_size, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*2 + height, grout_size))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*3 + height*2, grout_size))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*4 + height*3, grout_size))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*5 + height*4, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*6 + height*5, grout_size))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*7 + height*6, grout_size))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*8 + height*7, grout_size))
                
                # Right side - vertical stack
                result.paste(tile[0], (grout_size*9 + height*8, grout_size))
                result.paste(tile[1], (grout_size*9 + height*8, grout_size*2 + height))
                result.paste(tile[2], (grout_size*9 + height*8, grout_size*3 + height*2))
                result.paste(tile[3], (grout_size*9 + height*8, grout_size*4 + height*3))
                result.paste(tile[0], (grout_size*9 + height*8, grout_size*5 + height*4))
                result.paste(tile[1], (grout_size*9 + height*8, grout_size*6 + height*5))
                result.paste(tile[2], (grout_size*9 + height*8, grout_size*7 + height*6))
                result.paste(tile[3], (grout_size*9 + height*8, grout_size*8 + height*7))
                
                # Left side - vertical stack
                result.paste(tile[0], (grout_size, grout_size*2 + width))
                result.paste(tile[1], (grout_size, grout_size*3 + width + height))
                result.paste(tile[2], (grout_size, grout_size*4 + width + height*2))
                result.paste(tile[3], (grout_size, grout_size*5 + width + height*3))
                result.paste(tile[0], (grout_size, grout_size*6 + width + height*4))
                result.paste(tile[1], (grout_size, grout_size*7 + width + height*5))
                result.paste(tile[2], (grout_size, grout_size*8 + width + height*6))
                result.paste(tile[3], (grout_size, grout_size*9 + width + height*7))
                
                # Bottom row - horizontal tiles
                result.paste(tile[0].rotate(90, expand=True), (grout_size*2 + height*8, grout_size*9 + height*8))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*3 + height*9, grout_size*9 + height*8))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*4 + height*10, grout_size*9 + height*8))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*5 + height*11, grout_size*9 + height*8))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*6 + height*12, grout_size*9 + height*8))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*7 + height*13, grout_size*9 + height*8))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*8 + height*14, grout_size*9 + height*8))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*9 + height*15, grout_size*9 + height*8))
            
            elif ratio == 9:
                # First row - vertical tiles
                result.paste(tile[0].rotate(90, expand=True), (grout_size, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*2 + height, grout_size))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*3 + height*2, grout_size))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*4 + height*3, grout_size))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*5 + height*4, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*6 + height*5, grout_size))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*7 + height*6, grout_size))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*8 + height*7, grout_size))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*9 + height*8, grout_size))
                
                # Right side - vertical stack
                result.paste(tile[0], (grout_size*10 + height*9, grout_size))
                result.paste(tile[1], (grout_size*10 + height*9, grout_size*2 + height))
                result.paste(tile[2], (grout_size*10 + height*9, grout_size*3 + height*2))
                result.paste(tile[3], (grout_size*10 + height*9, grout_size*4 + height*3))
                result.paste(tile[0], (grout_size*10 + height*9, grout_size*5 + height*4))
                result.paste(tile[1], (grout_size*10 + height*9, grout_size*6 + height*5))
                result.paste(tile[2], (grout_size*10 + height*9, grout_size*7 + height*6))
                result.paste(tile[3], (grout_size*10 + height*9, grout_size*8 + height*7))
                result.paste(tile[0], (grout_size*10 + height*9, grout_size*9 + height*8))
                
                # Left side - vertical stack
                result.paste(tile[0], (grout_size, grout_size*2 + width))
                result.paste(tile[1], (grout_size, grout_size*3 + width + height))
                result.paste(tile[2], (grout_size, grout_size*4 + width + height*2))
                result.paste(tile[3], (grout_size, grout_size*5 + width + height*3))
                result.paste(tile[0], (grout_size, grout_size*6 + width + height*4))
                result.paste(tile[1], (grout_size, grout_size*7 + width + height*5))
                result.paste(tile[2], (grout_size, grout_size*8 + width + height*6))
                result.paste(tile[3], (grout_size, grout_size*9 + width + height*7))
                result.paste(tile[0], (grout_size, grout_size*10 + width + height*8))
                
                # Bottom row - horizontal tiles
                result.paste(tile[0].rotate(90, expand=True), (grout_size*2 + height*9, grout_size*10 + height*9))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*3 + height*10, grout_size*10 + height*9))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*4 + height*11, grout_size*10 + height*9))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*5 + height*12, grout_size*10 + height*9))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*6 + height*13, grout_size*10 + height*9))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*7 + height*14, grout_size*10 + height*9))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*8 + height*15, grout_size*10 + height*9))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*9 + height*16, grout_size*10 + height*9))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*10 + height*17, grout_size*10 + height*9))
            
            elif ratio == 10:
                # First row - vertical tiles
                result.paste(tile[0].rotate(90, expand=True), (grout_size, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*2 + height, grout_size))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*3 + height*2, grout_size))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*4 + height*3, grout_size))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*5 + height*4, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*6 + height*5, grout_size))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*7 + height*6, grout_size))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*8 + height*7, grout_size))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*9 + height*8, grout_size))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*10 + height*9, grout_size))
                
                # Right side - vertical stack
                result.paste(tile[0], (grout_size*11 + height*10, grout_size))
                result.paste(tile[1], (grout_size*11 + height*10, grout_size*2 + height))
                result.paste(tile[2], (grout_size*11 + height*10, grout_size*3 + height*2))
                result.paste(tile[3], (grout_size*11 + height*10, grout_size*4 + height*3))
                result.paste(tile[0], (grout_size*11 + height*10, grout_size*5 + height*4))
                result.paste(tile[1], (grout_size*11 + height*10, grout_size*6 + height*5))
                result.paste(tile[2], (grout_size*11 + height*10, grout_size*7 + height*6))
                result.paste(tile[3], (grout_size*11 + height*10, grout_size*8 + height*7))
                result.paste(tile[0], (grout_size*11 + height*10, grout_size*9 + height*8))
                result.paste(tile[1], (grout_size*11 + height*10, grout_size*10 + height*9))
                
                # Left side - vertical stack
                result.paste(tile[0], (grout_size, grout_size*2 + width))
                result.paste(tile[1], (grout_size, grout_size*3 + width + height))
                result.paste(tile[2], (grout_size, grout_size*4 + width + height*2))
                result.paste(tile[3], (grout_size, grout_size*5 + width + height*3))
                result.paste(tile[0], (grout_size, grout_size*6 + width + height*4))
                result.paste(tile[1], (grout_size, grout_size*7 + width + height*5))
                result.paste(tile[2], (grout_size, grout_size*8 + width + height*6))
                result.paste(tile[3], (grout_size, grout_size*9 + width + height*7))
                result.paste(tile[0], (grout_size, grout_size*10 + width + height*8))
                result.paste(tile[1], (grout_size, grout_size*11 + width + height*9))
                
                # Bottom row - horizontal tiles
                result.paste(tile[0].rotate(90, expand=True), (grout_size*2 + height*10, grout_size*11 + height*10))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*3 + height*11, grout_size*11 + height*10))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*4 + height*12, grout_size*11 + height*10))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*5 + height*13, grout_size*11 + height*10))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*6 + height*14, grout_size*11 + height*10))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*7 + height*15, grout_size*11 + height*10))
                result.paste(tile[2].rotate(90, expand=True), (grout_size*8 + height*16, grout_size*11 + height*10))
                result.paste(tile[3].rotate(90, expand=True), (grout_size*9 + height*17, grout_size*11 + height*10))
                result.paste(tile[0].rotate(90, expand=True), (grout_size*10 + height*18, grout_size*11 + height*10))
                result.paste(tile[1].rotate(90, expand=True), (grout_size*11 + height*19, grout_size*11 + height*10))
        elif layout_type == 'hexagon':
            result.paste(tile[0], (0 - width // 2, grout_size), tile[0]) #1
            result.paste(tile[1], (0 - width // 2, grout_size*2 + height), tile[1]) #2
            result.paste(tile[2], (0 - width // 2, grout_size*3 + height*2), tile[2]) #3
            result.paste(tile[3], (hexpercent + grout_size, 0 - height // 2), tile[3]) #4
            result.paste(tile[0], (hexpercent + grout_size, height // 2 + grout_size), tile[0]) #5
            result.paste(tile[1], (hexpercent + grout_size, height // 2 + height + grout_size*2), tile[1]) #6
            result.paste(tile[3], (hexpercent + grout_size, height // 2 + height*2 + grout_size*3), tile[2]) #7
            result.paste(tile[0], (width + grout_size*5, grout_size), tile[3]) #8
            result.paste(tile[1], (width + grout_size*5, grout_size*2 + height), tile[0]) #9
            result.paste(tile[2], (width + grout_size*5, grout_size*3 + height*2), tile[1]) #10
            result.paste(tile[1], (width + hexpercent*3 + grout_size*4, 0 - height // 2 + grout_size), tile[1]) #11
            result.paste(tile[2], (width + hexpercent*3 + grout_size*4, height // 2 + grout_size*2), tile[2]) #12
            result.paste(tile[3], (width + hexpercent*3 + grout_size*4, height // 2 + height + grout_size*3), tile[3]) #13
            result.paste(tile[1], (width + hexpercent*3 + grout_size*4, height // 2 + height*2 + grout_size*4), tile[1]) #14
            result.paste(tile[0], (width*2 + hexpercent*2 + grout_size*8, grout_size), tile[0])
            result.paste(tile[1], (width*2 + hexpercent*2 + grout_size*8, grout_size*2 + height), tile[1]) #15
            result.paste(tile[2], (width*2 + hexpercent*2 + grout_size*8, grout_size*3 + height*2), tile[2]) #16
        elif layout_type in ['third', 'vertThird']:
            result.paste(tile[0], (0 - quarter, grout_size)) #1
            result.paste(tile[0], (quarter*3 + grout_size, grout_size)) #2
            result.paste(tile[1], (0 - half, grout_size + height + grout_size)) #3
            result.paste(tile[1], (half + grout_size, grout_size + height + grout_size)) #4
            result.paste(tile[2], (0 - quarter*3, grout_size + height + grout_size + height + grout_size)) #5
            result.paste(tile[2], (quarter + grout_size, grout_size + height + grout_size + height + grout_size)) #6
            pass
        else:
            result.paste(tile[0], (grout_size, grout_size)) #1
            result.paste(tile[1], (grout_size + width + grout_size, grout_size)) #2
            result.paste(tile[2], (grout_size, grout_size + height + grout_size)) #3
            result.paste(tile[3], (grout_size + width + grout_size, grout_size + height + grout_size)) #4

        draw = ImageDraw.Draw(result)

        # Set standard mid point grout line
        vertical_midpoint = (width * 2 + grout_size) // 2
        
        # Work out the vertical grout lines for Brick Bond
        vertical_midpoint_left = (width * 2 + grout_size) // 4
        vertical_midpoint_right = ((width * 2 + grout_size) * 0.75)

        if layout_type == 'herringbone':
            if ratio == 2:
                draw.rectangle([height, width + grout_size * 2, height + grout_size // 2, width * 2 + grout_size], fill=grout_colour)
            elif ratio == 3:
                draw.rectangle([grout_size + height*4, grout_size*2 + height, grout_size*2 + height*4, grout_size*3 + height*5], fill=grout_colour)
                draw.rectangle([height*2, grout_size*4 + height*4, grout_size // 2 + height*2, grout_size*4 + height*6], fill=grout_colour)
                draw.rectangle([grout_size // 2 + height, 0, grout_size + height, grout_size*2 + height*2], fill=grout_colour)
            elif ratio == 4:
                draw.rectangle([grout_size + height*5, grout_size*3 + height*3, grout_size*1.5 + height*5, grout_size*4 + height*7], fill=grout_colour)
                draw.rectangle([height*3, grout_size*4 + height*5, grout_size // 2 + height*3, grout_size*6 + height*8], fill=grout_colour)
                draw.rectangle([height*2, height*6, grout_size // 2 + height*2, grout_size*3 + height*8], fill=grout_colour)
            elif ratio == 5:
                draw.rectangle([height*5, grout_size*5 + height*4, grout_size // 2 + height*5, grout_size*6 + height*5], fill=grout_colour)
                draw.rectangle([height*4 - grout_size, grout_size*5 + height*5, height*4 - grout_size // 2, grout_size*6 + height*6], fill=grout_colour)
                draw.rectangle([height*3 - grout_size*2, grout_size*6 + height*6, height*3 - grout_size*1.5, grout_size*7 + height*7], fill=grout_colour)
                draw.rectangle([height*2 - grout_size*3, grout_size*7 + height*7, height*2 - grout_size*2.5, grout_size*8 + height*8], fill=grout_colour)
                draw.rectangle([height - grout_size*4, grout_size*8 + height*8, height - grout_size*3.5, grout_size*9 + height*10], fill=grout_colour)
                draw.rectangle([height + grout_size, 0, height + grout_size*1.5, height*4 + grout_size*4], fill=grout_colour)
                draw.rectangle([height*2 + grout_size*2, 0, height*2 + grout_size*2.5, height*3 + grout_size*3], fill=grout_colour)
            elif ratio == 6:
                draw.rectangle([height*6, grout_size*6 + height*5, grout_size // 2 + height*6, grout_size*6 + height*6], fill=grout_colour)
                draw.rectangle([height*5 - grout_size, grout_size*7 + height*6, height*5 - grout_size // 2, grout_size*7 + height*7], fill=grout_colour)
                draw.rectangle([height*4 - grout_size*2, grout_size*8 + height*7, height*4 - grout_size*1.5, grout_size*8 + height*8], fill=grout_colour)
                draw.rectangle([height*3 - grout_size*3, grout_size*9 + height*8, height*3 - grout_size*2.5, grout_size*9 + height*9], fill=grout_colour)
                draw.rectangle([height*2 - grout_size*4, grout_size*10 + height*9, height*2 - grout_size*3.5, grout_size*10 + height*10], fill=grout_colour)
                draw.rectangle([height - grout_size*5, grout_size*11 + height*10, height - grout_size*4.5, grout_size*12 + height*12], fill=grout_colour)
                draw.rectangle([height, 0, height + grout_size // 2, grout_size*5 + height*5], fill=grout_colour)
            elif ratio == 10:
                draw.rectangle([height*1 + grout_size*1, 0, height*1 + grout_size*1.5, height*9 + grout_size*9], fill=grout_colour)
                draw.rectangle([height*1 - grout_size*9, height*19 + grout_size*8, height*1 - grout_size*8.5, height*20 + grout_size*8], fill=grout_colour)

        if layout_type in ['vertStacked', 'vertBrick', 'vertThird']:
            result = result.rotate(90, expand=True)
            result = result.transpose(Image.FLIP_TOP_BOTTOM)

        img_byte_arr = io.BytesIO()
        result.save(img_byte_arr, format='PNG')
        img_byte_arr.seek(0)

        if layout_type == 'stacked':
            layout = "Horizontal Block"
        if layout_type == 'brickBond':
            layout = "Horizontal Half Block"
        if layout_type == 'third':
            layout = "Horizontal Quarter Block"
        if layout_type == 'vertStacked':
            layout = "Vertical Block"
        if layout_type == 'vertBrick':
            layout = "Vertical Half Block"
        if layout_type == 'vertThird':
            layout = "Vertical Quarter Block"
        if layout_type == 'basketWeave':
            layout = "Basket Weave"
        if layout_type == 'herringbone':
            layout = "Herringbone"
        if layout_type == 'hexagon':
            layout = 'Hexagon'

        if grout_colour == '#d1d1cf':	
            grout_text = 'Gunmetal'
        if grout_colour == '#473938':	
            grout_text = 'Dovetail'
        if grout_colour == '#9e9fa4':	
            grout_text = 'Smoke'
        if grout_colour == '#516d71':	
            grout_text = 'Tornado Sky'
        if grout_colour == '#817670':	
            grout_text = 'Taupe Grey'
        if grout_colour == '#485a68':	
            grout_text = 'Storm Grey'
        if grout_colour == '#414550':	
            grout_text = 'Anthracite'
        if grout_colour == '#211f20':	
            grout_text = 'Ebony'
        if grout_colour == '#ffffff':	
            grout_text = 'White'
        if grout_colour == '#f5eed4':	
            grout_text = 'Jasmine'
        if grout_colour == '#dac9b7':	
            grout_text = 'Pebble'
        if grout_colour == '#76480d':	
            grout_text = 'Walnut'
        if grout_colour == '#623d13':	
            grout_text = 'Hazel'
        if grout_colour == '#623619':	
            grout_text = 'Mahogany'
        if grout_colour == '#cadee5':	
            grout_text = 'Cornflower White'
        if grout_colour == '#b6d6cb':	
            grout_text = 'Peppermint'
        if grout_colour == '#f2c7c0':	
            grout_text = 'Pink Champagne'
        if grout_colour == '#fbf6cc':	
            grout_text = 'Primrose'
        if grout_colour == '#ece1ab':	
            grout_text = 'Cream'
        if grout_colour == '#e0cdbc':	
            grout_text = 'Bahama Beige'
        if grout_colour == '#efe3d3':	
            grout_text = 'Jasmine'
        if grout_colour == '#cdc9bd':	
            grout_text = 'Limestone'
        if grout_colour == '#5e5b54':	
            grout_text = 'Taupe'
        if grout_colour == '#716152':	
            grout_text = 'Brown'
        if grout_colour == '#afb3b4':	
            grout_text = 'Silver Grey'
        if grout_colour == '#a6acac':	
            grout_text = 'Mid-Grey'
        if grout_colour == '#8d9193':	
            grout_text = 'Grey'
        if grout_colour == '#4c5157':	
            grout_text = 'Charcoal'
        if grout_colour == '#000000':	
            grout_text = 'Black'


        return send_file(img_byte_arr, mimetype='image/png', as_attachment=True, download_name='{} ({}x{}) ({} Grout) ({}).png'.format(tile_name, tile_size_width, tile_size_height, grout_text, layout))
    
    except Exception as e:
        return str(e), 400
    
if __name__ == '__main__':
    app.run(debug=True)
from flask import Flask, render_template, request, send_file, send_from_directory
from PIL import Image, ImageDraw, ImageColor
from random import randrange
import io

app = Flask(__name__)

@app.route('/favicon.ico')
@app.route('/robots.txt')
@app.route('/sitemap.xml')
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

        image_objects = [Image.open(img) for img in images[:4]]
        tile = [img.resize((tile_size_width, tile_size_height), Image.Resampling.LANCZOS) for img in image_objects]

        width, height = tile[0].size
        ratio = width / height

        if layout_type == 'basketWeave':
            if ratio == 3:
                result = Image.new('RGB', (width * 2 + grout_size * 4, height * 6 + grout_size), rgb)
            elif ratio == 2:
                result = Image.new('RGB', (width*2 + grout_size*4, width*2 + grout_size*4), rgb)
        elif layout_type == 'herringbone':
            result = Image.new('RGB', (width * 2 + grout_size * 2, width * 2 + grout_size * 2), rgb)
        elif layout_type in ['third', 'vertThird']:
            result = Image.new('RGB', (width + grout_size, height * 3 + grout_size * 3), rgb)
        else:
            result = Image.new('RGB', (width*2 + grout_size*2, height*2 + grout_size*2), rgb)

        draw = ImageDraw.Draw(result)
        vertical_midpoint = (width * 2 + grout_size * 2)
        horizontal_midpoint = (height * 2 + grout_size * 2) if layout_type != 'basketWeave' else (height * 3 + grout_size) if ratio == 3 else (height * 2 + grout_size)

        quarter = result // 4
        half = result // 2
        third = result // 3
        twothird = third * 2

        #Paste Images
        if layout_type in ['brickBond', 'vertBrick']:
            result.paste(tile[randrange(4)], (0 - half, grout_size)) #1
            result.paste(tile[randrange(4)], (half + grout_size, grout_size)) #2
            result.paste(tile[randrange(4)], (half + grout_size + width + grout_size, grout_size)) #3
            result.paste(tile[randrange(4)], (grout_size, grout_size + height + grout_size)) #4
            result.paste(tile[randrange(4)], (grout_size + width + grout_size, grout_size + height + grout_size)) #5
        elif layout_type == 'herringbone':
            if ratio == 2:
                result.paste(tile[randrange(4)].rotate(90, expand=True), (grout_size, 0 - height + grout_size))
                result.paste(tile[randrange(4)], (height + grout_size, grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (width + height + grout_size, grout_size))
                result.paste(tile[randrange(4)], (grout_size, height + grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (width + grout_size, height + grout_size))
                result.paste(tile[randrange(4)], (0 - height + grout_size, width + grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height + grout_size, width + grout_size))
                result.paste(tile[randrange(4)], (height * 3 + grout_size, width + grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (grout_size, height + width + grout_size))
                result.paste(tile[randrange(4)], (width + grout_size, height * 3 + grout_size))
            elif ratio == 3:
                result.paste(tile[randrange(4)].rotate(90, expand=True), (grout_size, 0 - width + height + grout_size))
                result.paste(tile[randrange(4)], (height + grout_size, grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height + width + grout_size + grout_size, grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (width + height * 2 + grout_size * 2, 0 - height))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (width + grout_size, height + grout_size))
                result.paste(tile[randrange(4)], (grout_size, height + grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height * 2 + grout_size, height * 2 + grout_size + grout_size))
                result.paste(tile[randrange(4)], (0 - height, height * 2 + grout_size + grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height, width + grout_size * 2))
                result.paste(tile[randrange(4)], (0 - twothird - grout_size, width + grout_size * 2))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (0 - grout_size, width + height + grout_size * 2 + grout_size))
                result.paste(tile[randrange(4)], (width + height * 2 + grout_size * 2, twothird + grout_size))
                result.paste(tile[randrange(4)], (width + height + grout_size + grout_size, width + grout_size))
                result.paste(tile[randrange(4)], (width + grout_size, width + height + grout_size + grout_size))
                result.paste(tile[randrange(4)], (twothird + grout_size, width + twothird + grout_size * 2))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (width + twothird + grout_size, width + twothird + grout_size * 2))
                pass
        elif layout_type == 'basketWeave':
            if ratio == 2:
                result.paste(tile[randrange(4)].rotate(90, expand=True), (grout_size, grout_size)) #1
                #result.paste(tile[randrange(4)].rotate(90, expand=True), (grout_size + height + grout_size, grout_size)) #2
                #result.paste(tile[randrange(4)], (grout_size + height + grout_size + height + grout_size, grout_size)) #3
                #result.paste(tile[randrange(4)], (grout_size + height + grout_size + height + grout_size, grout_size + height + grout_size)) #4
                #result.paste(tile[randrange(4)], (grout_size, grout_size + width + grout_size)) #5
                #result.paste(tile[randrange(4)], (grout_size, grout_size + width + grout_size + height + grout_size)) #6
                #result.paste(tile[randrange(4)].rotate(90, expand=True), (grout_size + height + grout_size + height + grout_size, grout_size + height + grout_size + height + grout_size)) #7
                #result.paste(tile[randrange(4)].rotate(90, expand=True), (grout_size + height + grout_size + height + grout_size, grout_size + height + grout_size + height + grout_size + height + grout_size)) #8
            elif ratio == 3:
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height * 2 + grout_size // 2, grout_size // 2))
                result.paste(tile[randrange(4)], (width + grout_size // 2, height * 2 + grout_size))
                result.paste(tile[randrange(4)], (grout_size // 2, height * 3 + grout_size))
                result.paste(tile[randrange(4)], (grout_size // 2, height * 4 + grout_size))
                result.paste(tile[randrange(4)], (grout_size // 2, height * 5 + grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height * 4 + grout_size // 2, height * 3 + grout_size // 2))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height * 5 + grout_size // 2, height * 3 + grout_size // 2))
                pass
        elif layout_type in ['third', 'vertThird']:
            result.paste(tile[randrange(4)], (0 - third, grout_size)) #1
            result.paste(tile[randrange(4)], (twothird + grout_size, grout_size)) #2
            result.paste(tile[randrange(4)], (0 - half, grout_size + height + grout_size)) #3
            result.paste(tile[randrange(4)], (half + grout_size, grout_size + height + grout_size)) #4
            result.paste(tile[randrange(4)], (0 - twothird, grout_size + height + grout_size + height + grout_size)) #5
            result.paste(tile[randrange(4)], (third + grout_size, grout_size + height + grout_size + height + grout_size)) #6
            pass
        else:
            result.paste(tile[randrange(4)], (grout_size, grout_size)) #1
            result.paste(tile[randrange(4)], (grout_size + width + grout_size, grout_size)) #2
            result.paste(tile[randrange(4)], (grout_size, grout_size + height + grout_size)) #3
            result.paste(tile[randrange(4)], (grout_size + width + grout_size, grout_size + height + grout_size)) #4

        img_byte_arr = io.BytesIO()
        result.save(img_byte_arr, format='PNG')
        img_byte_arr.seek(0)

        return send_file(img_byte_arr, mimetype='image/png', as_attachment=True, download_name=f'{tile_name}.png')
    
    except Exception as e:
        return str(e), 400
    
if __name__ == '__main__':
    app.run(debug=True)
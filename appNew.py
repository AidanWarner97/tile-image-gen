from flask import Flask, render_template, request, send_file, send_from_directory
from PIL import Image, ImageDraw, ImageColor
from random import randrange
import logging
from logging import StreamHandler
import io

app = Flask(__name__)

console_handler = StreamHandler()
console_handler.setLevel(logging.DEBUG)
console_handler.setFormatter(logging.Formatter(
    '%(asctime)s %(levelname)s: %(message)s [in %(pathname)s:%(lineno)d]'
))

app.logger.addHandler(console_handler)
app.logger.setLevel(logging.DEBUG)

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
                result = Image.new('RGB', (width*2 + grout_size*3, width*2 + grout_size*3), rgb)
        elif layout_type == 'herringbone':
            if ratio == 3:
                result = Image.new('RGB', (width*2 + grout_size*3, width*2 + grout_size*3), rgb)
            else:
                result = Image.new('RGB', (width*2 + grout_size, width*2 + grout_size), rgb)
        elif layout_type in ['third', 'vertThird']:
            result = Image.new('RGB', (width + grout_size, height * 3 + grout_size * 3), rgb)
        else:
            result = Image.new('RGB', (width*2 + grout_size*2, height*2 + grout_size*2), rgb)

        draw = ImageDraw.Draw(result)
        vertical_midpoint = (width * 2 + grout_size * 2)
        horizontal_midpoint = (height * 2 + grout_size * 2) if layout_type != 'basketWeave' else (height * 3 + grout_size) if ratio == 3 else (height * 2 + grout_size)

        quarter = width // 4
        half = width // 2
        third = width // 3
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
                result.paste(tile[randrange(4)], (height + grout_size + grout_size, grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height + width + grout_size + grout_size + grout_size, grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (width + height * 2 + grout_size * 4, 0 - height))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (width + grout_size*2, height + grout_size*2))
                result.paste(tile[randrange(4)], (grout_size, height + grout_size*2))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height * 2 + grout_size, height * 2 + grout_size*3))
                result.paste(tile[randrange(4)], (0 - height, height * 2 + grout_size*3))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height, width + grout_size*4))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (0 - grout_size, width + height + grout_size*5))
                result.paste(tile[randrange(4)], (0 - third*2 - grout_size, width + grout_size*4))
                result.paste(tile[randrange(4)], (width + height + grout_size*3, width + grout_size*2))
                result.paste(tile[randrange(4)], (width + grout_size*2, width + height + grout_size*3))
                result.paste(tile[randrange(4)], (twothird + grout_size, width + twothird + grout_size*4))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (width + twothird + grout_size*2, width + twothird + grout_size*4))
                result.paste(tile[randrange(4)], (width + twothird + grout_size*4, twothird + grout_size))
                pass
        elif layout_type == 'basketWeave':
            if ratio == 2:
                result.paste(tile[randrange(4)].rotate(90, expand=True), (grout_size, grout_size)) #1
                result.paste(tile[randrange(4)].rotate(90, expand=True), (grout_size + height + grout_size, grout_size)) #2
                result.paste(tile[randrange(4)], (grout_size + height + grout_size + height + grout_size, grout_size)) #3
                result.paste(tile[randrange(4)], (grout_size + height + grout_size + height + grout_size, grout_size + height + grout_size)) #4
                result.paste(tile[randrange(4)], (grout_size, grout_size + width + grout_size)) #5
                result.paste(tile[randrange(4)], (grout_size, grout_size + width + grout_size + height + grout_size)) #6
                result.paste(tile[randrange(4)].rotate(90, expand=True), (grout_size + height + grout_size + height, grout_size + height + grout_size + height + grout_size)) #7
                result.paste(tile[randrange(4)].rotate(90, expand=True), (grout_size + height + grout_size + height + grout_size + height, grout_size + height + grout_size + height + grout_size)) #8
            elif ratio == 3:
                result.paste(tile[randrange(4)].rotate(90, expand=True), (grout_size, grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height + grout_size*2, grout_size))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height*2 + grout_size*3, grout_size))
                result.paste(tile[randrange(4)], (height*3 + grout_size*4, grout_size))
                result.paste(tile[randrange(4)], (height*3 + grout_size*4, height + grout_size*2))
                result.paste(tile[randrange(4)], (height*3 + grout_size*4, height*2 + grout_size*3))
                result.paste(tile[randrange(4)], (grout_size, height*3 + grout_size*2))
                result.paste(tile[randrange(4)], (grout_size, height*4 + grout_size*3))
                result.paste(tile[randrange(4)], (grout_size, height*5 + grout_size*4))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height*3 + grout_size*2, height*3 + grout_size*4))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height*4 + grout_size*3, height*3 + grout_size*4))
                result.paste(tile[randrange(4)].rotate(90, expand=True), (height*5 + grout_size*4, height*3 + grout_size*4))
                pass
        elif layout_type in ['third', 'vertThird']:
            result.paste(tile[randrange(4)], (0 - third, grout_size)) #1
            result.paste(tile[randrange(4)], (third*2 + grout_size, grout_size)) #2
            result.paste(tile[randrange(4)], (0 - half, grout_size + height + grout_size)) #3
            result.paste(tile[randrange(4)], (half + grout_size, grout_size + height + grout_size)) #4
            result.paste(tile[randrange(4)], (0 - third*2, grout_size + height + grout_size + height + grout_size)) #5
            result.paste(tile[randrange(4)], (third + grout_size, grout_size + height + grout_size + height + grout_size)) #6
            pass
        else:
            result.paste(tile[randrange(4)], (grout_size, grout_size)) #1
            result.paste(tile[randrange(4)], (grout_size + width + grout_size, grout_size)) #2
            result.paste(tile[randrange(4)], (grout_size, grout_size + height + grout_size)) #3
            result.paste(tile[randrange(4)], (grout_size + width + grout_size, grout_size + height + grout_size)) #4

        draw = ImageDraw.Draw(result)

        # Set standard mid point grout line
        vertical_midpoint = (width * 2 + grout_size) // 2
        
        # Work out the vertical grout lines for Brick Bond
        vertical_midpoint_left = (width * 2 + grout_size) // 4
        vertical_midpoint_right = ((width * 2 + grout_size) * 0.75)

        if layout_type == 'herringbone':
            if ratio == 2:
                draw.rectangle([vertical_midpoint_left - grout_size // 2, 0, vertical_midpoint_left + grout_size // 2, height], fill=grout_colour)
                draw.rectangle([vertical_midpoint_right - grout_size // 2, 0, vertical_midpoint_right + grout_size // 2, width], fill=grout_colour)
                draw.rectangle([0, height - grout_size // 2, height * 3 + grout_size // 2, height + grout_size // 2], fill=grout_colour)
                draw.rectangle([height * 3, width, width * 2 + grout_size, width + grout_size // 2], fill=grout_colour)
                draw.rectangle([0, width, width,  width + grout_size // 2], fill=grout_colour)
                draw.rectangle([width - grout_size // 2, height, width + grout_size // 2, width * 2 + grout_size], fill=grout_colour)
                draw.rectangle([height - grout_size // 2, width + grout_size, height + grout_size // 2, width * 2 + grout_size * 2], fill=grout_colour)
                draw.rectangle([0, height + width - grout_size // 2, height + grout_size // 2, height + width + grout_size // 2], fill=grout_colour)
                draw.rectangle([width + grout_size, height * 3 - grout_size // 2, width * 2 + grout_size * 2, height * 3 + grout_size // 2], fill=grout_colour)
                draw.rectangle([height * 3, width + grout_size, height * 3 + grout_size // 2, width + height + grout_size // 2], fill=grout_colour)

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
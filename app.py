from flask import Flask, render_template, request, send_file, send_from_directory
from PIL import Image, ImageDraw, ImageColor
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
        '''
        if 'checked' in request.form.getlist('groutEnabled'):
            grout_size_str = request.form.get('groutSize', '')
            if grout_size_str.isdigit():
                grout_size = int(grout_size_str)
            else:
                grout_size = 0
        else:
            grout_size = 0
        '''
        grout_size = int(request.form.get('groutSize', 0))
        tile_size_width = int(request.form.get('tileWidth', 0))
        tile_size_height = int(request.form.get('tileHeight', 0))
        grout_color = request.form.get('groutColor', '#000000')
        rgb = ImageColor.getcolor(grout_color, "RGB")
        tile_name = request.form.get('tileName', 'tile_image_result')

        images = request.files.getlist('images')

        # Count number of images, and duplicate if only provided 1
        while len(images) < 4:
            images.append(images[0])

        image_objects = [Image.open(img) if hasattr(img, 'read') else Image.open(img.filename) for img in images[:4]]

        images_resized = [img.resize((tile_size_width, tile_size_height), Image.ANTIALIAS) for img in image_objects]

        width, height = images_resized[0].size
        ratio = width / height
        
        # Determine layout
        layout_type = request.form.get('layoutType','stacked')

        # Create a blank image with a transparent background
        if grout_size == 1:
            if layout_type == 'basketWeave':
                if ratio == 3:
                    result_image = Image.new('RGBA', (width * 2 + grout_size // 2, height * 6 + grout_size // 2), rgb)
                elif ratio == 2:
                    result_image = Image.new('RGBA', (width * 2 + grout_size // 2, height * 4 + grout_size // 2), rgb)
            elif layout_type == 'herringbone':
                if ratio == 2:
                    result_image = Image.new('RGBA', (width * 2 + grout_size, width * 2 + grout_size), rgb)
                elif ratio == 3:
                    result_image = Image.new('RGBA', (width * 2 + grout_size * 2, width * 2 + grout_size * 2), rgb)
            elif layout_type == 'third' or layout_type == 'vertThird':
                result_image = Image.new('RGBA', (width + grout_size // 2, height * 3 + grout_size * 2), rgb)
            else:
                result_image = Image.new('RGBA', (width * 2 + grout_size // 2, height * 2 + grout_size // 2), rgb)
        else:
            if layout_type == 'basketWeave':
                if ratio == 3:
                    result_image = Image.new('RGBA', (width * 2 + grout_size, height * 6 + grout_size), rgb)
                elif ratio == 2:
                    result_image = Image.new('RGBA', (width * 2 + grout_size, height * 4 + grout_size), rgb)
            elif layout_type == 'herringbone':
                result_image = Image.new('RGBA', (width * 2 + grout_size * 2, width * 2 + grout_size * 2), rgb)
            elif layout_type == 'third' or layout_type == 'vertThird':
                result_image = Image.new('RGBA', (width + grout_size, height * 3 + grout_size * 3), rgb)
            else:
                result_image = Image.new('RGB', (width * 2 + grout_size, height * 2 + grout_size), rgb)

        quarter = width // 4
        half = width // 2
        third = width // 3
        twothird = third * 2

        # Paste images onto the result image with the layout offset
        if layout_type == 'brickBond' or layout_type == 'vertBrick':
            result_image.paste(images_resized[0], (grout_size - width // 2, grout_size // 2))
            result_image.paste(images_resized[1], (width // 2 + grout_size, grout_size // 2))
            result_image.paste(images_resized[0], (width + width // 2 + grout_size, grout_size // 2))
            result_image.paste(images_resized[2], (0, height + grout_size))
            result_image.paste(images_resized[3], (width + grout_size, height + grout_size))
        elif layout_type == 'herringbone':
            if ratio == 2:
                result_image.paste(images_resized[0].rotate(90, expand=True), (grout_size // 2, 0 - height + grout_size // 2))
                result_image.paste(images_resized[1], (height + grout_size // 2, 0 + grout_size // 2))
                result_image.paste(images_resized[0].rotate(90, expand=True), (width + height + grout_size, grout_size // 2))
                result_image.paste(images_resized[1], (grout_size // 2, height + grout_size // 2))
                result_image.paste(images_resized[0].rotate(90, expand=True), (width + grout_size // 2, height + grout_size // 2))
                result_image.paste(images_resized[1], (0 - height + grout_size // 2, width + grout_size // 2))
                result_image.paste(images_resized[0].rotate(90, expand=True), (height + grout_size // 2, width + grout_size))
                result_image.paste(images_resized[1], (height * 3 + grout_size // 2, width + grout_size // 2))
                result_image.paste(images_resized[0].rotate(90, expand=True), (grout_size // 2, width + height + grout_size // 2))
                result_image.paste(images_resized[1], (width + grout_size, height * 3 + grout_size))
            elif ratio == 3:
                result_image.paste(images_resized[0].rotate(90, expand=True), (grout_size // 2, 0 - width + height + grout_size // 2))
                result_image.paste(images_resized[1], (height + grout_size, grout_size // 2))
                result_image.paste(images_resized[0].rotate(90, expand=True), (height + width + grout_size + grout_size // 2, grout_size // 2))
                result_image.paste(images_resized[1].rotate(90, expand=True), (width + height * 2 + grout_size * 2, 0 - height))
                result_image.paste(images_resized[0].rotate(90, expand=True), (width + grout_size, height + grout_size))
                result_image.paste(images_resized[1], (grout_size // 2, height + grout_size))
                result_image.paste(images_resized[0].rotate(90, expand=True), (height * 2 + grout_size // 2, height * 2 + grout_size + grout_size // 2))
                result_image.paste(images_resized[1], (0 - height, height * 2 + grout_size + grout_size // 2))
                result_image.paste(images_resized[0].rotate(90, expand=True), (height, width + grout_size * 2))
                result_image.paste(images_resized[1], (0 - twothird - grout_size // 2, width + grout_size * 2))
                result_image.paste(images_resized[0].rotate(90, expand=True), (0 - grout_size // 2, width + height + grout_size * 2 + grout_size // 2))
                result_image.paste(images_resized[1], (width + height * 2 + grout_size * 2, twothird + grout_size // 2))
                result_image.paste(images_resized[0], (width + height + grout_size + grout_size // 2, width + grout_size))
                result_image.paste(images_resized[1], (width + grout_size, width + height + grout_size + grout_size // 2))
                result_image.paste(images_resized[0], (twothird + grout_size // 2, width + twothird + grout_size * 2))
                result_image.paste(images_resized[1].rotate(90, expand=True), (width + twothird + grout_size, width + twothird + grout_size * 2))
        elif layout_type == 'basketWeave':
            result_image.paste(images_resized[0].rotate(90, expand=True), (grout_size // 2, grout_size))
            result_image.paste(images_resized[1].rotate(90, expand=True), (height + grout_size // 2, grout_size))
            result_image.paste(images_resized[2], (width + grout_size // 2, grout_size // 2))
            result_image.paste(images_resized[3], (width + grout_size // 2, height + grout_size))
            result_image.paste(images_resized[3], (grout_size // 2, height * 2 + grout_size))
            result_image.paste(images_resized[2], (grout_size // 2, height * 3 + grout_size))
            result_image.paste(images_resized[1].rotate(90, expand=True), (width + grout_size // 2, width + grout_size // 2))
            result_image.paste(images_resized[0].rotate(90, expand=True), (height * 3 + grout_size // 2, width + grout_size // 2))
            if ratio == 3:
                result_image.paste(images_resized[2].rotate(90, expand=True), (height * 2 + grout_size // 2, grout_size // 2))
                result_image.paste(images_resized[3], (width + grout_size // 2, height * 2 + grout_size))
                result_image.paste(images_resized[3], (grout_size // 2, height * 3 + grout_size))
                result_image.paste(images_resized[3], (grout_size // 2, height * 4 + grout_size))
                result_image.paste(images_resized[3], (grout_size // 2, height * 5 + grout_size))
                result_image.paste(images_resized[1].rotate(90, expand=True), (height * 4 + grout_size // 2, height * 3 + grout_size // 2))
                result_image.paste(images_resized[1].rotate(90, expand=True), (height * 5 + grout_size // 2, height * 3 + grout_size // 2))
        elif layout_type == 'third' or layout_type == 'vertThird':
            result_image.paste(images_resized[0], (grout_size // 2 - quarter, grout_size // 2))
            result_image.paste(images_resized[0], (width - quarter + grout_size, grout_size // 2))
            result_image.paste(images_resized[1], (0 - half - grout_size // 2, height + grout_size))
            result_image.paste(images_resized[1], (half + grout_size // 2, height + grout_size))
            result_image.paste(images_resized[2], (0 - quarter * 3, height * 2 + grout_size))
            result_image.paste(images_resized[2], (quarter + grout_size, height * 2 + grout_size))
        else:
            result_image.paste(images_resized[0], (grout_size // 2, grout_size // 2))
            result_image.paste(images_resized[1], (width + grout_size // 2, grout_size // 2))
            result_image.paste(images_resized[2], (grout_size // 2, height + grout_size // 2))
            result_image.paste(images_resized[3], (width + grout_size // 2, height + grout_size // 2))


        # Create a drawing object to draw grout lines
        draw = ImageDraw.Draw(result_image)

        # Draw horizontal grout line at the midpoint
        if layout_type == 'basketWeave':
            if ratio == 3:
                horizontal_midpoint = (height * 3 + grout_size)
            elif ratio == 2:
                horizontal_midpoint = (height * 2 + grout_size)
        else:
            horizontal_midpoint = (height * 2 + grout_size) // 2

        if layout_type != 'herringbone':
            draw.rectangle([0, horizontal_midpoint, width * 2, horizontal_midpoint + grout_size // 2], fill=grout_color)

        # Set standard mid point grout line
        vertical_midpoint = (width * 2 + grout_size) // 2
        
        # Work out the vertical grout lines for Brick Bond
        vertical_midpoint_left = (width * 2 + grout_size) // 4
        vertical_midpoint_right = ((width * 2 + grout_size) * 0.75)

        # Draw the grout lines required
        if grout_size == 1:
            if layout_type == 'brickBond' or layout_type == 'vertBrick':
                draw.rectangle([vertical_midpoint_left, 0, vertical_midpoint_left + grout_size // 2, height], fill=grout_color)
                draw.rectangle([vertical_midpoint_right, 0, vertical_midpoint_right + grout_size // 2, height], fill=grout_color)
                draw.rectangle([vertical_midpoint, height + grout_size, vertical_midpoint + grout_size // 2, height * 2 + grout_size], fill=grout_color)
                draw.rectangle([0, height + grout_size, 0, height * 2 + grout_size], fill=grout_color)
            elif layout_type == 'herringbone':
                if ratio == 2:
                    draw.rectangle([vertical_midpoint_left - grout_size // 2, 0, vertical_midpoint_left + grout_size // 2, height], fill=grout_color)
                    draw.rectangle([vertical_midpoint_right - grout_size // 2, 0, vertical_midpoint_right + grout_size // 2, width], fill=grout_color)
                    draw.rectangle([0, height - grout_size // 2, height * 3 + grout_size // 2, height + grout_size // 2], fill=grout_color)
                    draw.rectangle([height * 3, width, width * 2 + grout_size, width + grout_size // 2], fill=grout_color)
                    draw.rectangle([0, width, width,  width + grout_size // 2], fill=grout_color)
                    draw.rectangle([width - grout_size // 2, height, width + grout_size // 2, width * 2 + grout_size], fill=grout_color)
                    draw.rectangle([height - grout_size // 2, width + grout_size, height + grout_size // 2, width * 2 + grout_size * 2], fill=grout_color)
                    draw.rectangle([0, height + width - grout_size // 2, height + grout_size // 2, height + width + grout_size // 2], fill=grout_color)
                    draw.rectangle([width + grout_size, height * 3 - grout_size // 2, width * 2 + grout_size * 2, height * 3 + grout_size // 2], fill=grout_color)
                    draw.rectangle([height * 3, width + grout_size, height * 3 + grout_size // 2, width + height + grout_size // 2], fill=grout_color)
                #elif ratio == 3:
                    #draw.rectangle([0, 0, height, height], fill=grout_color)
            elif layout_type == 'basketWeave':
                draw.rectangle([grout_size // 2 + height, grout_size, grout_size // 2 + height, width + grout_size], fill=grout_color) # Top-left Vertical
                draw.rectangle([grout_size // 2 + width, grout_size, grout_size // 2 + height * ratio, width * 2 + grout_size // 2], fill=grout_color) # Vertical
                draw.rectangle([width + grout_size, height + grout_size // 2, width * 2 + grout_size, height + grout_size // 2], fill=grout_color) # Right Horizontal
                if ratio == 3:
                    draw.rectangle([grout_size // 2 + height * 2, grout_size, grout_size // 2 + height * 2, width + grout_size], fill=grout_color) # Top-left Vertical 2
                    draw.rectangle([grout_size // 2 + height * 4, width + grout_size, height * 4 + grout_size // 2, height * 4 + width], fill=grout_color) # Bottom-right Vertical 1
                    draw.rectangle([grout_size // 2 + height * 5, width + grout_size, height * 5 + grout_size // 2, height * 5 + width], fill=grout_color) # Bottom-right Vertical 2
                    draw.rectangle([width + grout_size // 2, height * 2, width * 2 + grout_size, height * 2 + grout_size // 2], fill=grout_color) # Right Horizontal 2
                    draw.rectangle([grout_size // 2, height * 4 + grout_size // 2, width + grout_size, height * 4 + grout_size // 2], fill=grout_color) # Bottom-left Horizontal 1
                    draw.rectangle([grout_size // 2, height * 5 + grout_size // 2, width + grout_size, height * 5 + grout_size // 2], fill=grout_color) # Bottom-left Horizontal 2
                elif ratio == 2:
                    draw.rectangle([grout_size // 2, height * 3 + grout_size // 2, width, height * 3 + grout_size // 2], fill=grout_color) # Bottom-left Horizontal
                    draw.rectangle([grout_size // 2 + height * 3, width + grout_size, height * 3, height * 3 + width + grout_size // 2], fill=grout_color) # Bottom-right Vertical
            elif layout_type == 'third' or layout_type == 'vertThird':
                draw.rectangle([0, height + grout_size // 2, width, height + grout_size // 2], fill=grout_color)
                draw.rectangle([0, height * 2 + grout_size - grout_size // 2, width * 2 + grout_size, height * 2 + grout_size + grout_size // 2], fill=grout_color)
                draw.rectangle([half + quarter, 0, half + quarter + grout_size // 2, height + grout_size // 2], fill=grout_color)
                draw.rectangle([half - grout_size // 2, height + grout_size, half + grout_size // 2, height * 2], fill=grout_color)
                draw.rectangle([quarter, height * 2 + grout_size, quarter + grout_size // 2, height * 3 + grout_size], fill=grout_color)
            else:
                draw.rectangle([vertical_midpoint - grout_size // 2, 0, vertical_midpoint + grout_size // 2, height * 2], fill=grout_color)
        else:
            if layout_type == 'brickBond' or layout_type == 'vertBrick':
                draw.rectangle([vertical_midpoint_left - grout_size // 2, 0, vertical_midpoint_left + grout_size // 2, height], fill=grout_color) # Top-left Vertical
                draw.rectangle([vertical_midpoint_right - grout_size // 2, 0, vertical_midpoint_right + grout_size // 2, height], fill=grout_color) # Top-right Vertical
                draw.rectangle([vertical_midpoint - grout_size // 2, height + grout_size, vertical_midpoint + grout_size // 2, height * 2 + grout_size], fill=grout_color) # Bottom Middle
                draw.rectangle([0, 0 + height + grout_size, 0 + grout_size // 2, 0 + height * 2 + grout_size], fill=grout_color) # Bottom Left
                draw.rectangle([width * 2 + grout_size, height + grout_size // 2, width * 2 + grout_size // 2, 0 + height * 2 + grout_size], fill=grout_color) # Bottom Right
            elif layout_type == 'herringbone':
                if ratio == 2:
                    draw.rectangle([vertical_midpoint_left - grout_size // 2, 0, vertical_midpoint_left + grout_size // 2, height], fill=grout_color)
                    draw.rectangle([vertical_midpoint_right - grout_size // 2, 0, vertical_midpoint_right + grout_size // 2, width], fill=grout_color)
                    draw.rectangle([0, height - grout_size // 2, height * 3 + grout_size // 2, height + grout_size // 2], fill=grout_color)
                    draw.rectangle([height * 3, width, width * 2 + grout_size, width + grout_size], fill=grout_color)
                    draw.rectangle([0, width, width,  width + grout_size], fill=grout_color)
                    draw.rectangle([width - grout_size // 2, height, width + grout_size // 2, width * 2 + grout_size], fill=grout_color)
                    draw.rectangle([height - grout_size // 2, width + grout_size, height + grout_size // 2, width * 2 + grout_size * 2], fill=grout_color)
                    draw.rectangle([0, height + width - grout_size // 2, height + grout_size // 2, height + width + grout_size // 2], fill=grout_color)
                    draw.rectangle([width + grout_size, height * 3 - grout_size // 2, width * 2 + grout_size * 2, height * 3 + grout_size // 2], fill=grout_color)
                    draw.rectangle([height * 3, width + grout_size, height * 3 + grout_size, width + height + grout_size // 2], fill=grout_color)
                #elif ratio == 3:
                    #draw.rectangle([0, 0, height, height], fill=grout_color)
            elif layout_type == 'basketWeave':
                draw.rectangle([grout_size // 2 + height, grout_size, grout_size + height, width + grout_size], fill=grout_color) # Top-left Vertical
                draw.rectangle([grout_size // 2 + width, grout_size, grout_size + height * ratio, width * 2 + grout_size // 2], fill=grout_color) # Vertical
                draw.rectangle([width + grout_size, height + grout_size // 2, width * 2 + grout_size, height + grout_size], fill=grout_color) # Right Horizontal
                if ratio == 3:
                    draw.rectangle([grout_size // 2 + height * 2, grout_size, grout_size + height * 2, width + grout_size], fill=grout_color) # Top-left Vertical 2
                    draw.rectangle([grout_size // 2 + height * 4, width + grout_size, height * 4 + grout_size, height * 4 + width], fill=grout_color) # Bottom-right Vertical 1
                    draw.rectangle([grout_size // 2 + height * 5, width + grout_size, height * 5 + grout_size, height * 5 + width], fill=grout_color) # Bottom-right Vertical 2
                    draw.rectangle([width + grout_size // 2, height * 2, width * 2 + grout_size, height * 2 + grout_size // 2], fill=grout_color) # Right Horizontal 2
                    draw.rectangle([grout_size // 2, height * 4, width + grout_size, height * 4 + grout_size // 2], fill=grout_color) # Bottom-left Horizontal 1
                    draw.rectangle([grout_size // 2, height * 5, width + grout_size, height * 5 + grout_size // 2], fill=grout_color) # Bottom-left Horizontal 2
                elif ratio == 2:
                    draw.rectangle([grout_size // 2, height * 3 + grout_size // 2, width, height * 3 + grout_size], fill=grout_color) # Bottom-left Horizontal
                    draw.rectangle([grout_size // 2 + height * 3, width + grout_size, height * 3, height * 3 + width + grout_size // 2], fill=grout_color) # Bottom-right Vertical
            elif layout_type == 'third' or layout_type == 'vertThird':
                draw.rectangle([0, height + grout_size - grout_size // 2, width, height + grout_size + grout_size // 2], fill=grout_color)
                draw.rectangle([0, height * 2 + grout_size - grout_size // 2, width * 2 + grout_size, height * 2 + grout_size + grout_size // 2], fill=grout_color)
                draw.rectangle([half + quarter + grout_size - grout_size // 2, 0, half + quarter + grout_size + grout_size // 2, height + grout_size // 2], fill=grout_color)
                draw.rectangle([half - grout_size // 2, height + grout_size, half + grout_size // 2, height * 2], fill=grout_color)
                draw.rectangle([quarter + grout_size - grout_size // 2, height * 2 + grout_size, quarter + grout_size + grout_size // 2, height * 3 + grout_size], fill=grout_color)
            else:
                draw.rectangle([vertical_midpoint - grout_size // 2, 0, vertical_midpoint + grout_size // 2, height * 2], fill=grout_color)

        # Draw border around the image with half the thickness of grout lines
        border_thickness = grout_size // 2
        if grout_size == 1:
            if layout_type == 'brickBond' or layout_type == 'vertBrick':
                draw.rectangle([0, 0, width * 2 + grout_size // 2, border_thickness], fill=grout_color)
            elif layout_type == 'herringbone':
                draw.rectangle([height + grout_size, 0, width * 2 + grout_size, border_thickness], fill=grout_color) # Top
                draw.rectangle([width * 2 + grout_size // 2 + border_thickness, 0, width * 2 + grout_size * 2, width + grout_size], fill=grout_color) # Right top half
                draw.rectangle([width * 2 + grout_size + border_thickness, height * 3 - grout_size + border_thickness, width * 2 + grout_size, width * 2 + grout_size + border_thickness], fill=grout_color) # Right bottom quarter
            elif layout_type == 'basketWeave':
                if ratio == 3:
                    draw.rectangle([0, 0, width * 2 + grout_size, grout_size // 2],fill=grout_color) # Top
                    draw.rectangle([0, 0, grout_size // 2, height * 6 + grout_size * 2],fill=grout_color) # Left
                else:
                    draw.rectangle([0, 0, width * 2 + grout_size, grout_size // 2],fill=grout_color) # Top
                    draw.rectangle([0, 0, grout_size // 2, height * 4 + grout_size * 2],fill=grout_color) # Left
            elif layout_type == 'third' or layout_type == 'vertThird':
                draw.rectangle([0, 0, width * 2 + grout_size // 2, border_thickness], fill=grout_color) # Top
            else:
                draw.rectangle([0, 0, width * 2 + grout_size, grout_size // 2],fill=grout_color) # Top
                draw.rectangle([0, 0, grout_size // 2, height * 2 + grout_size // 2],fill=grout_color) # Left
        else:
            if layout_type == 'brickBond' or layout_type == 'vertBrick':
                draw.rectangle([0, 0, width * 2 + grout_size // 2, border_thickness], fill=grout_color) # Top
                draw.rectangle([0, height * 2 + grout_size // 2, width * 2 + grout_size, height * 2 + grout_size], fill=grout_color) # Bottom
            elif layout_type == 'herringbone':
                draw.rectangle([height + grout_size, 0, width * 2 + grout_size, border_thickness], fill=grout_color) # Top
                draw.rectangle([width * 2 + grout_size // 2 + border_thickness, 0, width * 2 + grout_size * 2, width + grout_size], fill=grout_color) # Right top half
                draw.rectangle([width * 2 + grout_size + border_thickness, height * 3 - grout_size + border_thickness, width * 2 + grout_size, width * 2 + grout_size + border_thickness], fill=grout_color) # Right bottom quarter
                draw.rectangle([height + grout_size, width * 2 + grout_size, width * 2 + grout_size + border_thickness, width * 2 + grout_size + border_thickness], fill=grout_color) # Bottom
                draw.rectangle([0, 0, border_thickness, width + grout_size // 2 + border_thickness], fill=grout_color) # Left top half
                draw.rectangle([0, height * 3 - grout_size + border_thickness, border_thickness, width * 2 + grout_size + border_thickness], fill=grout_color) # Left bottom half
            elif layout_type == 'basketWeave':
                if ratio == 3:
                    draw.rectangle([0, 0, width * 2 + grout_size, grout_size // 2],fill=grout_color) # Top
                    draw.rectangle([0, height * 6, width * 2 + grout_size, height * 6 + grout_size // 2],fill=grout_color) # Bottom
                    draw.rectangle([0, 0, grout_size // 2, height * 6 + grout_size * 2],fill=grout_color) # Left
                    draw.rectangle([width * 2, 0, width * 2 + grout_size, height * 6 + grout_size // 2],fill=grout_color) # Right
                else:
                    draw.rectangle([0, 0, width * 2 + grout_size, grout_size // 2],fill=grout_color) # Top
                    draw.rectangle([0, height * 4, width * 2 + grout_size, height * 4 + grout_size // 2],fill=grout_color) # Bottom
                    draw.rectangle([0, 0, grout_size // 2, height * 4 + grout_size * 2],fill=grout_color) # Left
                    draw.rectangle([width * 2, 0, width * 2 + grout_size, height * 4 + grout_size // 2],fill=grout_color) # Right
            elif layout_type == 'third' or layout_type == 'vertThird':
                draw.rectangle([0, 0, width * 2 + grout_size // 2, border_thickness], fill=grout_color) # Top
                draw.rectangle([0, height * 3 + grout_size // 2, width * 2 + grout_size, height * 3 + grout_size], fill=grout_color) # Bottom
            else:
                draw.rectangle([0, 0, width * 2 + grout_size, grout_size // 2],fill=grout_color) # Top
                draw.rectangle([0, height * 2 + grout_size, width * 2 + grout_size, height * 2 + grout_size // 2],fill=grout_color) # Bottom
                draw.rectangle([0, 0, grout_size // 2, height * 2 + grout_size * 2],fill=grout_color) # Left
                draw.rectangle([width * 2, 0, width * 2 + grout_size, height * 2 + grout_size // 2],fill=grout_color) # Right
        
        # Finalise images

        if layout_type == 'basketWeave':
            if ratio == 3:
                result_image = result_image.resize((int(tile_size_width * 2 + grout_size), int(tile_size_height * 6 + grout_size)), Image.ANTIALIAS)
            else:
                result_image = result_image.resize((int(tile_size_width * 2 + grout_size), int(tile_size_height * 4 + grout_size)), Image.ANTIALIAS)
        elif layout_type == 'herringbone':
            if ratio == 2:    
                result_image = result_image.resize((int(tile_size_width * 2 + grout_size * 2), int(tile_size_height * 4 + grout_size * 2)), Image.ANTIALIAS)
            elif ratio == 3:
                result_image = result_image.resize((int(tile_size_width * 2 + grout_size * 2), int(tile_size_height * 6 + grout_size * 2)), Image.ANTIALIAS)
        elif layout_type == 'third':
            result_image = result_image.resize((int(tile_size_width + grout_size * 2), int(height * 3 + grout_size * 2)), Image.ANTIALIAS)
            if layout_type == 'vertThird':
                result_image = result_image.rotate(90, expand=True)
        elif layout_type == 'vertStacked':
            result_image = result_image.rotate(90, expand=True)
            result_image = result_image.resize((int(tile_size_height * 2 + grout_size), int(tile_size_width * 2 + grout_size)), Image.ANTIALIAS)
        else:
            # Resize the image based on the specified tile size
            result_image = result_image.resize((int(tile_size_width * 2 + grout_size), int(tile_size_height * 2 + grout_size)), Image.ANTIALIAS)

        # Save the result to a BytesIO object
        result_buffer = io.BytesIO()
        result_image.save(result_buffer, format='PNG')
        result_buffer.seek(0)

        return send_file(result_buffer, as_attachment=True, attachment_filename='{} {}x{}.png'.format(tile_name, tile_size_height, tile_size_width))
    except Exception as e:
        print('Error:', str(e))
        return 'Internal Server Error', 500

if __name__ == '__main__':
    app.run(debug=True)

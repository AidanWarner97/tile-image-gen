<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Generate custom tile pattern sheets from your tile images.">
  <title>Tile Image Generator</title>
  <link rel="icon" type="image/x-icon" href="logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="static/style.css">
  <!-- Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-SJ4XFG0ZS9"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-SJ4XFG0ZS9');
  </script>
</head>
<body>
  <header class="hero-header">
    <img src="logo.png" alt="Tile Image Generator Logo" height="100" />
    <div id="header-title">
      <h1>TILE IMAGE GENERATOR</h1>
      <p>Production layout tool</p>
    </div>
  </header>

  <nav class="top-nav" aria-label="Main navigation">
    <a href="/">Generator</a>
    <a href="/updates">All Updates</a>
    <a href="/feedback">Feedback</a>
  </nav>

  <main class="sections-wrap">
    <section id="generator" class="content-section">
      <div class="section-title">
        <h2>GENERATOR</h2>
      </div>
      <div class="section-content">
        <form id="generator-form" action="generate.php" method="POST" enctype="multipart/form-data">
          <div class="form-block">
            <h3>Tile Name</h3>
            <label>
              Tile name
              <input type="text" name="tileName" required placeholder="e.g. Charlie Blue">
            </label>
          </div>

          <div class="form-block">
            <h3>Upload Files</h3>
            <div class="upload-panel">
              <label id="drop-area" for="images" class="drop-area">
                <span class="drop-area-icon" aria-hidden="true">+</span>
                <span id="drop-area-text" class="drop-area-text">Drop files to attach, or <span class="browse-link">browse</span></span>
              </label>
              <input id="images" type="file" name="images[]" accept="image/*" multiple required>
              <span id="upload-note">No files selected</span>
            </div>
          </div>

          <div class="form-block">
            <h3>Settings</h3>
            <div class="settings-subhead">Tile Sizes</div>
            <div class="grid two">
              <label>
                Width
                <input type="number" name="tileWidth" min="1" step="1" required placeholder="200">
              </label>
              <label>
                Height
                <input type="number" name="tileHeight" min="1" step="1" required placeholder="100">
              </label>
            </div>
            <p id="ratio-warning" class="form-warning" hidden>Herringbone currently supports ratios up to 6:1. Higher ratios are temporarily disabled.</p>

            <div class="settings-subhead">Layout</div>
            <div class="grid one">
              <label>
                Layout pattern
                <select name="layoutType" id="layoutType" required>
                  <option value="stacked">Horizontal Block</option>
                  <option value="brickBond">Horizontal 1/2 Block</option>
                  <option value="third">Horizontal 1/3 Block</option>
                  <option value="quarter">Horizontal 1/4 Block</option>
                  <option value="vertStacked">Vertical Block</option>
                  <option value="vertBrick">Vertical 1/2 Block</option>
                  <option value="vertThird">Vertical 1/3 Block</option>
                  <option value="vertQuarter">Vertical 1/4 Block</option>
                  <option value="basketWeave">Basket Weave</option>
                  <option value="herringbone">Herringbone</option>
                  <option value="hexagon">Hexagon</option>
                </select>
              </label>
            </div>

            <div class="settings-subhead">Grout Options</div>
            <div class="grid two">
              <label>
                Grout colour
                <select name="groutColour" required>
                  <option value="#d1d1cf">Gunmetal</option>
                  <option value="#9e9fa4">Smoke</option>
                  <option value="#473938">Dovetail</option>
                  <option value="#516d71">Tornado Sky</option>
                  <option value="#817670">Taupe Grey</option>
                  <option value="#485a68">Storm Grey</option>
                  <option value="#414550">Anthracite</option>
                  <option value="#211f20">Ebony</option>
                  <option value="#ffffff">White</option>
                  <option value="#f5eed4">Jasmine</option>
                  <option value="#dac9b7">Pebble</option>
                  <option value="#76480d">Walnut</option>
                  <option value="#623d13">Hazel</option>
                  <option value="#623619">Mahogany</option>
                  <option value="#cadee5">Cornflower White</option>
                  <option value="#b6d6cb">Peppermint</option>
                  <option value="#f2c7c0">Pink Champagne</option>
                  <option value="#fbf6cc">Primrose</option>
                  <option value="#ece1ab">Cream</option>
                  <option value="#e0cdbc">Bahama Beige</option>
                  <option value="#efe3d3">Jasmine</option>
                  <option value="#cdc9bd">Limestone</option>
                  <option value="#5e5b54">Taupe</option>
                  <option value="#716152">Brown</option>
                  <option value="#afb3b4">Silver Grey</option>
                  <option value="#a6acac">Mid-Grey</option>
                  <option value="#8d9193">Grey</option>
                  <option value="#4c5157">Charcoal</option>
                  <option value="#000000">Black</option>
                </select>
              </label>
              <label>
                Thickness
                <input type="number" name="groutSize" min="0" step="1" required placeholder="3">
              </label>
            </div>
          </div>

          <div class="form-block">
            <h3>Generate</h3>
            <button id="submit-btn" type="submit">Generate Tile Pattern</button>
          </div>
        </form>
      </div>
    </section>

    <section id="updates" class="content-section">
      <div class="section-title">
        <h2>UPDATES</h2>
      </div>
      <div class="section-content">
        <h3>Latest Update</h3>
        <div id="latest-post">Loading latest post...</div>
        <div class="section-viewmore"></div>
        <div id="view-more">
          <a id="updates-btn" href="/updates">View More Updates</a>
        </div>
      </div>
    </section>

    <!-- Advertisement -->
    <div id="ads" class="sidebar-ads">
      <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8424385314773719" crossorigin="anonymous"></script>
      <ins class="adsbygoogle"
            style="display:block"
            data-ad-format="fluid"
            data-ad-layout-key="-gw-3+1f-3d+2z"
            data-ad-client="ca-pub-8424385314773719"
            data-ad-slot="8630400101"></ins>
      <script>
        (adsbygoogle = window.adsbygoogle || []).push({});
      </script>
    </div>

  </main>

  <div id="result-modal" class="modal" hidden>
    <div class="modal-panel">
      <h3>Preview</h3>
      <p id="result-name" class="result-name"></p>
      <div class="result-preview-wrap">
        <img id="result-preview" class="result-preview" alt="Generated tile preview">
      </div>
      <p id="herringbone-tip" class="herringbone-tip" hidden>For standard herringbone orientation in CAD, set rotation to 45 or -45 degrees.</p>
      <div class="modal-actions">
        <button type="button" id="modal-close" class="btn-secondary">Close</button>
        <button type="button" id="modal-download">Download</button>
      </div>
    </div>
  </div>

  <footer class="site-footer">
    <p>&copy; <span id="year"></span> Aidan Warner. All rights reserved.</p>
  </footer>

  <script src="static/app.js"></script>
</body>
</html>
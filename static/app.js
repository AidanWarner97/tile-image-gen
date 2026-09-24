const form = document.getElementById("generator-form");
const images = document.getElementById("images");
const uploadNote = document.getElementById("upload-note");
const dropArea = document.getElementById("drop-area");
const dropAreaText = document.getElementById("drop-area-text");
const submitBtn = document.getElementById("submit-btn");
const latestPostEl = document.getElementById("latest-post");
const resultModal = document.getElementById("result-modal");
const modalClose = document.getElementById("modal-close");
const modalDownload = document.getElementById("modal-download");
const resultPreview = document.getElementById("result-preview");
const resultName = document.getElementById("result-name");
const herringboneTip = document.getElementById("herringbone-tip");
const layoutSelect = document.getElementById("layoutType");
const ratioWarning = document.getElementById("ratio-warning");
const tileWidthInput = form.elements.tileWidth;
const tileHeightInput = form.elements.tileHeight;
const imageSourceInputs = form.elements.imageSource;
const tileNameBlock = document.getElementById("tile-name-block");
const uploadSourceBlock = document.getElementById("upload-source-block");
const predefinedSourceBlock = document.getElementById("predefined-source-block");
const tileSizeFields = document.getElementById("tile-size-fields");
const predefinedBrand = document.getElementById("predefined-brand");
const predefinedRange = document.getElementById("predefined-range");
const predefinedVersion = document.getElementById("predefined-version");
const predefinedSize = document.getElementById("predefined-size");
let tileCatalogue = null;

resultModal.hidden = true;

let generatedUrl = "";
let generatedFilename = "tile-pattern.png";
let generationLogId = "";

function selectedImageSource() {
  return Array.from(imageSourceInputs).find((input) => input.checked)?.value || "upload";
}

function updatePredefinedNameAndSize() {
  const parts = [predefinedBrand, predefinedRange, predefinedVersion]
    .map((select) => select.selectedOptions[0]?.textContent.trim())
    .filter((value) => value && !value.startsWith("Choose"));
  const size = predefinedSize.selectedOptions[0];
  const dimensions = size?.dataset.width && size?.dataset.height ? `${size.dataset.width}x${size.dataset.height}` : "";
  form.elements.tileName.value = [...parts, dimensions].filter(Boolean).join(" ");
  if (dimensions) {
    tileWidthInput.value = size.dataset.width;
    tileHeightInput.value = size.dataset.height;
  }
}

function resetSelect(select, placeholder) {
  select.replaceChildren(new Option(placeholder, ""));
  select.value = "";
}

function populateSelect(select, items, placeholder) {
  select.replaceChildren(new Option(placeholder, ""));
  items.forEach((item) => select.add(new Option(item.name, item.id)));
  select.disabled = false;
}

function selectedCatalogueItem(items, id) {
  return items?.find((item) => item.id === id) || null;
}

function populateCatalogueBrands() {
  populateSelect(predefinedBrand, tileCatalogue.brands || [], "Choose a brand");
  resetSelect(predefinedRange, "Choose a range");
  resetSelect(predefinedVersion, "Choose a version");
  resetSelect(predefinedSize, "Choose a size");
  predefinedRange.disabled = true;
  predefinedVersion.disabled = true;
  predefinedSize.disabled = true;
}

function loadTileCatalogue() {
  fetch("catalogue/tiles.json", { cache: "force-cache" })
    .then((response) => {
      if (!response.ok) throw new Error("Catalogue unavailable");
      return response.json();
    })
    .then((catalogue) => {
      tileCatalogue = catalogue;
      populateCatalogueBrands();
    })
    .catch(() => {
      tileCatalogue = null;
      predefinedSourceBlock.querySelector(".form-warning").textContent = "The tile catalogue could not be loaded.";
    });
}

function updateImageSourceFields() {
  const predefined = selectedImageSource() === "predefined";
  uploadSourceBlock.hidden = predefined;
  predefinedSourceBlock.hidden = !predefined;
  tileNameBlock.hidden = predefined;
  tileSizeFields.hidden = predefined;
  images.required = !predefined;
  tileWidthInput.required = !predefined;
  tileHeightInput.required = !predefined;
  [predefinedBrand, predefinedRange, predefinedVersion, predefinedSize].forEach((select) => {
    select.disabled = !predefined;
    select.required = predefined && select !== predefinedSize;
  });
  if (predefined) {
    updatePredefinedNameAndSize();
  } else {
    images.required = true;
    tileWidthInput.required = true;
    tileHeightInput.required = true;
  }
  updateRatioWarning();
}

function isUnsupportedHerringboneRatio() {
  const width = Number(tileWidthInput.value);
  const height = Number(tileHeightInput.value);
  return layoutSelect.value === "herringbone" && height > 0 && width / height > 6;
}

function updateRatioWarning() {
  ratioWarning.hidden = !isUnsupportedHerringboneRatio();
}

function closeResultModal() {
  resultModal.hidden = true;
}

function setGeneratedPreview(blob, filename, showHerringboneTip) {
  if (generatedUrl) {
    URL.revokeObjectURL(generatedUrl);
  }

  generatedUrl = URL.createObjectURL(blob);
  generatedFilename = filename;
  resultPreview.src = generatedUrl;
  resultName.textContent = filename;
  herringboneTip.hidden = !showHerringboneTip;
  resultModal.hidden = false;
}

function updateUploadText(count) {
  if (count > 0) {
    const label = `${count} image${count > 1 ? "s" : ""} selected`;
    dropAreaText.textContent = label;
    uploadNote.textContent = "Click or drop again to replace your selection.";
    return;
  }

  uploadNote.textContent = "No files selected";
  dropAreaText.innerHTML = 'Drop files to attach, or <span class="browse-link">browse</span>';
}

images.addEventListener("change", () => {
  updateUploadText(images.files.length);
});

["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
  dropArea.addEventListener(eventName, (event) => {
    event.preventDefault();
    event.stopPropagation();
  });
});

["dragenter", "dragover"].forEach((eventName) => {
  dropArea.addEventListener(eventName, () => {
    dropArea.classList.add("drop-area--highlight");
  });
});

["dragleave", "drop"].forEach((eventName) => {
  dropArea.addEventListener(eventName, () => {
    dropArea.classList.remove("drop-area--highlight");
  });
});

dropArea.addEventListener("drop", (event) => {
  const fileList = event.dataTransfer?.files;
  if (!fileList || fileList.length === 0) {
    return;
  }

  images.files = fileList;
  updateUploadText(fileList.length);
});

function setLoading(loading) {
  submitBtn.disabled = loading;
  submitBtn.textContent = loading ? "Generating..." : "Generate Tile Pattern";
}

async function fetchLatestPost() {
  try {
    const response = await fetch("updates.php");
    const posts = await response.json();

    if (!Array.isArray(posts) || posts.length === 0) {
      latestPostEl.innerHTML = "<p>No posts found.</p>";
      return;
    }

    const post = posts[0];
    const clean = document.createElement("div");
    clean.innerHTML = post.excerpt?.rendered || "";
    let excerpt = (clean.textContent || "").replace(/\s+/g, " ").trim();
    const words = excerpt.split(" ").filter(Boolean);
    if (words.length > 30) {
      excerpt = `${words.slice(0, 30).join(" ")}...`;
    }

    latestPostEl.replaceChildren();

    const title = document.createElement("h4");
    const titleLink = document.createElement("a");
    titleLink.href = post.link;
    titleLink.target = "";
    titleLink.rel = "noopener";
    titleLink.textContent = post.title?.rendered || "Update";
    title.appendChild(titleLink);

    const topRule = document.createElement("hr");
    const metadata = document.createElement("h6");
    metadata.textContent = `${post.date || ""} | ${post.author || "Aidan Warner"}`;

    const bottomRule = document.createElement("hr");
    const excerptLine = document.createElement("p");
    excerptLine.className = "latest-post-excerpt";
    excerptLine.append(document.createTextNode(`${excerpt} `));

    const readMore = document.createElement("a");
    readMore.href = post.link;
    readMore.target = "";
    readMore.rel = "noopener";
    readMore.textContent = "Read more";
    excerptLine.appendChild(readMore);

    latestPostEl.append(title, topRule, metadata, bottomRule, excerptLine);
  } catch (error) {
    latestPostEl.innerHTML = "<p>Failed to load the latest post.</p>";
  }
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  updateRatioWarning();
  if (isUnsupportedHerringboneRatio()) {
    ratioWarning.scrollIntoView({ block: "center", behavior: "smooth" });
    return;
  }

  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  if (selectedImageSource() === "predefined") {
    event.preventDefault();
    alert("Predefined tile generation is not available yet.");
    return;
  }

  setLoading(true);
  const data = new FormData(form);

  try {
    const response = await fetch("generate.php", {
      method: "POST",
      body: data,
    });

    if (!response.ok) {
      const message = await response.text();
      throw new Error(message || "Generation failed");
    }

    const contentDisposition = response.headers.get("Content-Disposition") || "";
    generationLogId = response.headers.get("X-Generation-Log-Id") || "";
    const match = contentDisposition.match(/filename=\"?([^\"]+)\"?/i);
    const filename = match?.[1] || "tile-pattern.png";
    const blob = await response.blob();
    setGeneratedPreview(blob, filename, layoutSelect.value === "herringbone");
  } catch (error) {
    alert(error.message || "Error generating tile pattern. Please try again.");
  } finally {
    setLoading(false);
  }
});

layoutSelect.addEventListener("change", updateRatioWarning);
tileWidthInput.addEventListener("input", updateRatioWarning);
tileHeightInput.addEventListener("input", updateRatioWarning);
Array.from(imageSourceInputs).forEach((input) => input.addEventListener("change", updateImageSourceFields));
[predefinedBrand, predefinedRange, predefinedVersion, predefinedSize].forEach((select) => {
  select.addEventListener("change", updatePredefinedNameAndSize);
});
predefinedBrand.addEventListener("change", () => {
  const brand = selectedCatalogueItem(tileCatalogue?.brands, predefinedBrand.value);
  populateSelect(predefinedRange, brand?.ranges || [], "Choose a range");
  resetSelect(predefinedVersion, "Choose a version");
  resetSelect(predefinedSize, "Choose a size");
  predefinedVersion.disabled = true;
  predefinedSize.disabled = true;
  updatePredefinedNameAndSize();
});
predefinedRange.addEventListener("change", () => {
  const brand = selectedCatalogueItem(tileCatalogue?.brands, predefinedBrand.value);
  const range = selectedCatalogueItem(brand?.ranges, predefinedRange.value);
  populateSelect(predefinedVersion, range?.versions || [], "Choose a version");
  resetSelect(predefinedSize, "Choose a size");
  predefinedSize.disabled = true;
  updatePredefinedNameAndSize();
});
predefinedVersion.addEventListener("change", () => {
  const brand = selectedCatalogueItem(tileCatalogue?.brands, predefinedBrand.value);
  const range = selectedCatalogueItem(brand?.ranges, predefinedRange.value);
  const version = selectedCatalogueItem(range?.versions, predefinedVersion.value);
  const sizes = version?.sizes || [];
  predefinedSize.replaceChildren(new Option("Choose a size", ""));
  sizes.forEach((size) => {
    const option = new Option(size.name, size.id);
    option.dataset.width = String(size.width || "");
    option.dataset.height = String(size.height || "");
    predefinedSize.add(option);
  });
  predefinedSize.disabled = sizes.length === 0;
  updatePredefinedNameAndSize();
});

modalClose.addEventListener("click", () => {
  closeResultModal();
});

resultModal.addEventListener("click", (event) => {
  if (event.target === resultModal) {
    closeResultModal();
  }
});

modalDownload.addEventListener("click", () => {
  if (!generatedUrl) {
    return;
  }

  const a = document.createElement("a");
  a.href = generatedUrl;
  a.download = generatedFilename;
  document.body.appendChild(a);
  a.click();
  a.remove();

  if (generationLogId) {
    fetch("generate-download.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ request_id: generationLogId }),
      keepalive: true,
    }).catch(() => {});
  }
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    closeResultModal();
  }
});

document.getElementById("year").textContent = String(new Date().getFullYear());
updateUploadText(0);
updateRatioWarning();
updateImageSourceFields();
loadTileCatalogue();
fetchLatestPost();

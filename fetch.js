// Replace with your GitHub repository information
const owner = 'AidanWarner97';
const repo = 'tileimagegen-electron';

const searchTerms = ['arm64', 'Setup', 'Portable', 'rpm', 'deb'];

// Function to fetch the latest release from GitHub
async function fetchLatestRelease() {
    const apiUrl = `https://api.github.com/repos/${owner}/${repo}/releases/latest`;
    try {
        const response = await fetch(apiUrl);
        const release = await response.json();

        searchTerms.forEach(term => {
            const asset = release.assets.find(asset => asset.name.includes(term));

            if (asset) {
                const link = document.getElementById(term);
                link.href = asset.browser_download_url;
            } else {
                throw new Error(`No file found containing "${searchTerm}" in the latest release.`);
            }
        })
    } catch (error) {
        console.error('Error fetching latest release:', error);
        const downloadLink = document.getElementById('download-link');
        downloadLink.textContent = 'Error fetching latest release';
      }
}

// Fetch the latest release on page load
fetchLatestRelease();


const arm64 = 'arm64';
const installer = 'Setup';
const portable = 'Portable';
const rpm = 'rpm';
const deb = 'deb';
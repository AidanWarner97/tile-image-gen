<p align="center">
  <img src="https://github.com/AidanWarner97/tile-image-gen/blob/python3/static/logo.png?raw=true" width="100" />
</p>
<p align="center">
    <h1 align="center">TILE-IMAGE-GEN</h1>
</p>
<p align="center">
	<img src="https://img.shields.io/github/license/AidanWarner97/tile-image-gen?style=flat&color=0080ff" alt="license">
	<img src="https://img.shields.io/github/last-commit/AidanWarner97/tile-image-gen?style=flat&logo=git&logoColor=white&color=0080ff" alt="last-commit">
<p>
<p align="center">
		<em>Developed with the software and tools below.</em>
</p>
<p align="center">
	<img src="https://img.shields.io/badge/HTML5-E34F26.svg?style=flat&logo=HTML5&logoColor=white" alt="HTML5">
	<img src="https://img.shields.io/badge/Python-3776AB.svg?style=flat&logo=Python&logoColor=white" alt="Python">
</p>
<hr>

##  Quick Links

> - [ Overview](#overview)
> - [ Features](#features)
> - [ Repository Structure](#repository-structure)
> - [ Modules](#modules)
> - [ Getting Started](#getting-started)
>   - [ Installation](#installation)
>   - [ Running tile-image-gen](#running-tile-image-gen)
>   - [ Tests](#tests)
> - [ Project Roadmap](#project-roadmap)
> - [ Contributing](#contributing)
> - [ License](#license)

---

##  Overview

Create tile layouts from 1 or 4 variants using multiple layouts and various colour choices for grout colours.

---

##  Features

> - Only requires 1 image if only 1 variant is available, but supports 4 images if you have multiple variants.
> - Provide the name of the tile for a more understandable file name.
> - Select from multiple different layout options.
> - Add a grout line and your choice of grout colours from brands BAL and UltraTile.


---

##  Repository Structure

```sh
└── tile-image-gen/
    ├── LICENSE
    ├── app.py
    ├── wsgi.py
    ├── tile-image-gen.service
    ├── Dockerfile
    ├── install.sh
    ├── update.sh
    ├── requirements.txt
    ├── static
    │   ├── favicon.ico
    │   ├── robots.txt
    │   ├── info.svg
    │   ├── logo.png
    │   ├── logo_transparent.png
    │   ├── style.css
    │   └── sitemap.xml
    └── templates
        └── index.html
```

---

##  Modules

<details closed><summary>.</summary>

| File                                                                         | Summary                            |
| ---                                                                          | ---                                |
| [app.py](https://github.com/AidanWarner97/tile-image-gen/blob/python3/app.py) | (DEBUG) Backend for creating and merging uploaded files and adding grout lines |
| [wsgi.py](https://github.com/AidanWarner97/tile-image-gen/blob/python3/wsgi.py) | (PROD) Backend for creating and merging uploaded files and adding grout lines |

</details>

<details closed><summary>templates</summary>

| File                                                                                           | Summary                                          |
| ---                                                                                            | ---                                              |
| [index.html](https://github.com/AidanWarner97/tile-image-gen/blob/python3/templates/index.html) | Front end site for end users |

</details>

---

##  Getting Started

***Requirements***

Ensure you have the following dependencies installed on your system:

* **Python**: `version 3.11`

###  Installation

1. Clone the tile-image-gen repository:

```sh
git clone https://github.com/AidanWarner97/tile-image-gen
```

2. Change to the project directory:

```sh
cd tile-image-gen
```

3. Install the dependencies:

```sh
> sudo bash install.sh
```

###  Running tile-image-gen

Use the following command to run tile-image-gen:

```sh
> python app.py
```

### Installation

#### Automatic

For simpler install, run the install.sh
```sh
> sudo bash install.sh
> sudo systemctl enable --now tile-image-gen.service
```

#### Manual

Make sure to install the dependencies first
```sh
pip install -r requirements.txt
```

Then edit the service file
```sh
> nano tile-image-gen.service
```
Change the Working directory and ExecStart line to the location you want the script to run from.

```sh
WorkingDirectory=/opt/tile-image-gen
ExecStart=/opt/tile-image-gen/venv/bin/python /opt/tile-image-gen/wsgi.py
```
Then you can run the following commands:
```sh
> cp tile-image-gen.service /etc/systemd/system/
> systemctl daemon-reload
> systemctl enable --now tile-image-gen.service
```

#### Updating

Using the update.sh script, you can call it through a crontab to automatically update.

```sh
0 1 * * * /opt/tile-image-gen/update.sh
```
This example will automatically update the script at 1am every day.

---

## Docker

Docker support is here, with the repo being hosted [here](https://hub.docker.com/r/mraidanlw97/tile-image-gen)

```sh
docker run \
  -p 5000:5000 \
  -p restart=on-failure \
  mraidanlw97/tile-image-gen:python3
```


### Docker Compose

```sh
services:
  tile-image-gen:
    image: mraidanlw97/tile-image-gen:python3
    ports:
      - "5000:5000"
```

---

##  Project Roadmap

- [x] ` Add option for no grout-lines` - 65aa768
- [x] ` Upgrade codebase to Python3` - 65aa768
- [x] ` Enable docker support` - 1aa9864
- [x] ` Upgrade ratios for more than just herringbone ` - cbff5e7
- [x] ` Update web template ` - dfff3f8

Will add to this list as requests/issues come in

---

##  Contributing

Contributions are welcome! Here are several ways you can contribute:

- **Submit Pull Requests**: Review open PRs, and submit your own PRs.

<details closed>
    <summary>Contributing Guidelines</summary>

1. **Fork the Repository**: Start by forking the project repository to your GitHub account.
2. **Clone Locally**: Clone the forked repository to your local machine using a Git client.
   ```sh
   git clone https://github.com/AidanWarner97/tile-image-gen
   ```
3. **Create a New Branch**: Always work on a new branch, giving it a descriptive name.
   ```sh
   git checkout -b new-feature-x
   ```
4. **Make Your Changes**: Develop and test your changes locally.
5. **Commit Your Changes**: Commit with a clear message describing your updates.
   ```sh
   git commit -m 'Implemented new feature x.'
   ```
6. **Push to GitHub**: Push the changes to your forked repository.
   ```sh
   git push origin new-feature-x
   ```
7. **Submit a Pull Request**: Create a PR against the original project repository. Clearly describe the changes and their motivations.

Once your PR is reviewed and approved, it will be merged into the main branch.

</details>

---

##  License

This project is protected under the [AGPL](https://choosealicense.com/licenses/agpl-3.0/) License. For more details, refer to the [LICENSE](LICENSE) file.

[**Return**](#quick-links)

---

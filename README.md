<p align="center">
  <img src="https://github.com/AidanWarner97/tile-image-gen/blob/main/static/logo.png?raw=true" width="100" />
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
> - Add a grout line (minimum 2mm) and your choice of grout colours from brands BAL and UltraTile.


---

##  Repository Structure

```sh
└── tile-image-gen/
    ├── LICENSE
    ├── app.py
    ├── wsgi.py
    ├── tile-image-gen.service
    ├── Dockerfile
    ├── static
    │   ├── favicon.ico
    │   ├── robots.txt
    │   ├── info.svg
    │   ├── logo.png
    │   └── sitemap.xml
    └── templates
        └── index.html
```

---

##  Modules

<details closed><summary>.</summary>

| File                                                                         | Summary                            |
| ---                                                                          | ---                                |
| [app.py](https://github.com/AidanWarner97/tile-image-gen/blob/master/app.py) | Backend for creating and merging uploaded files and adding grout lines |

</details>

<details closed><summary>templates</summary>

| File                                                                                           | Summary                                          |
| ---                                                                                            | ---                                              |
| [index.html](https://github.com/AidanWarner97/tile-image-gen/blob/master/templates/index.html) | Front end site for end users |

</details>

---

##  Getting Started

***Requirements***

Ensure you have the following dependencies installed on your system:

* **Python**: `version 2.7`

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
> pip install -r requirements.txt
```

###  Running tile-image-gen

Use the following command to run tile-image-gen:

```sh
> python app.py
```

Alternatively, we've created a basic systemctl service file.  You'll need to make sure to edit the Exec line to have where you've cloned the repo in order for it to work properly

```sh
> cp tile-image-gen.service /etc/systemd/system/
> systemctl daemon-reload
> systemctl enable --now tile-image-gen.service
```

---

## Docker

Docker support is here, with the repo being hosted [here](https://hub.docker.com/r/mraidanlw97/tile-image-gen)

<details closed><summary>Standard Docker</summary>
```sh
docker run \
  -p 5000:5000
  -p restart=on-failure
  mraidanlw97/tile-image-gen:python2
```
</details>

<details closed><summary>Docker Compose</summary>
```sh
services:
  tile-image-gen:
    image: mraidanlw97/tile-image-gen:python2
    ports:
      - "5000:5000"
```
</details>

---

##  Project Roadmap

- [ ] ` Add option for no grout-lines`
- [ ] ` Upgrade codebase to Python3`
- [x] ` Enable docker support`

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

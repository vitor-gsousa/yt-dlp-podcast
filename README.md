# yt-dlp-podcast
Scrape audio from youtube and serve as a podcast.
This uses [yt-dlp] (https://github.com/yt-dlp/yt-dlp) to the hard work.  The result is then served as a podcast.
Forked from alnixon [youtube-dl-podcast] (https://github.com/alnixon/youtube-dl-podcast)

## Setup
A subdirectory caled **data** needs to be created with write permission for the user that will run the shell script.

The following files coordinate the downloading of audio
* **yt-dlp.sh (linux)**
  * This needs to be scheduled to run with cron every 24hrs.
  * Line 44/43 of this file will need to be changed to your path.
  * Initially 1 month of files will be downloaded - this can be changed on line 43/42.
  * Files will be kept for 60 days - this can be changed on line 53/56

* **channels.txt**
List the youtube channels to download here, one per line.
* **downloaded.txt**
This is used to track what has already been downloaded.

## Feed Options
This project provides two different PHP files to serve your podcast feed, each with its own use case:

### Option 1: index.php (Single Feed)
**Best for:** Simple setups with all channels in one podcast feed

**Features:**
* Generates a single RSS feed combining all downloaded content
* Works with a single `data/` directory
* Fixed podcast title: "YT-DLP Podcast"
* Simpler setup and configuration
* All channels appear in one unified feed

**Usage:**
```
http://your-domain.com/index.php
```

### Option 2: feed.php (Multi-Feed)
**Best for:** Advanced setups with separate feeds for different channel groups

**Features:**
* Supports multiple independent podcast feeds
* Each feed uses its own subfolder (e.g., `yt-ciencia/data/`, `tech-news/data/`)
* Dynamic podcast titles based on folder name (e.g., "yt-ciencia" → "YT - Ciencia")
* Can serve external audio links (from JSON metadata) if local files are missing
* Supports both m4a and mp3 formats
* Better error handling with validation

**Usage:**
```
http://your-domain.com/feed.php?feed=folder-name
```

**Example:**
```
http://your-domain.com/feed.php?feed=yt-ciencia
http://your-domain.com/feed.php?feed=tech-news
```

**Setup for feed.php:**
1. Create separate folders for each feed (e.g., `yt-ciencia/`, `tech-news/`)
2. Inside each folder, create a `data/` subdirectory
3. Modify your download script to save files to the appropriate feed folder
4. Point your podcast client to the feed URL with the correct `?feed=` parameter

## Usage
Simply point your podcast client at the location this is hosted at using either index.php or feed.php (with appropriate parameters) based on your chosen setup.

## Contributing
I knocked this together *very* quickly for my own needs.  I welcome improvements via pull requests.

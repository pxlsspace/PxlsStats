# Important note
This is built specifically for pxls.space. If your pxls.space install is older than Sept 8, 2018, then you'll be missing some columns and tables on your database. See this commit for details: [xSke/Pxls 4f22e](https://github.com/xSke/Pxls/commit/4f22e996bc7bbbb39649300c0214dea15a619a43)

# Important note 2
This is shitty guide only for local testing, because no normal guide since 2019  

# Getting up and running
## Required tools
* [PHP](https://php.net/)
## Configuration
1) Rename `config.example.php` to `config.php`
2) Modify `config.php` with all the necessary values (config/etc). It should looks like this:
```
    $DB_HOST = "127.0.0.1";
    $DB_USER = "postgres";
    $DB_PASSWORD = "your_password";
    $DB_DATABASE = "pxls";
    $INSTANCE_URL = "http://localhost:4567";
```
3) Change all "href" started with "//" to "https://" in `index.html`
4) Change "whoamiRoot" in `main.js` (line 19) to "http://localhost:4567/" (or different port where your pxls instance works)
5) Generate stats.json from database:
    * `php cron.php`

## Running
1) Start browser without CORS in terminal:  
  
**Windows:**  
  
* `"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe" --allow-file-access-from-files --disable-web-security --disable-gpu --user-data-dir=%LOCALAPPDATA%\Google\chromeTemp`  
    or  
* `"C:\Program Files\Google\Chrome\Application\chrome.exe" --allow-file-access-from-files --disable-web-security --disable-gpu --user-data-dir=%LOCALAPPDATA%\Google\chromeTemp`  
  
**Linux:**  
  
* `google-chrome --allow-file-access-from-files --disable-web-security`  
  
**OSX:**  
  
* `open -n -a /Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --args --user-data-dir="/tmp/chrome_dev_test" --allow-file-access-from-files --disable-web-security`  
  
2) Put `index.html` in browser window

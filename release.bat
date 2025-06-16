@echo off
setlocal enabledelayedexpansion
REM V1.1
REM ─────────────────────────────────────────────────────
REM CONFIGURATION
REM ─────────────────────────────────────────────────────
SET "PLUIGN_NAME=MainWP Cloudflare Bridge"
SET "PLUGIN_TAGS=MainWP, Cloudflare, Pro-report"
SET "HEADER_SCRIPT=C:\Ignore By Avast\0. PATHED Items\Plugins\deployscripts\myplugin_headers.php"
SET "PLUGIN_DIR=C:\Users\Nathan\Git\MainWP-Bridge-to-Cloudflare-Bridge\main-wp-to-cloudflare-bridge-extension\"
IF "%PLUGIN_DIR:~-1%"=="\" SET "PLUGIN_DIR=%PLUGIN_DIR:~0,-1%"
SET "PLUGIN_FILE=%PLUGIN_DIR%\main-wp-to-cloudflare-bridge-extension.php"
SET "CHANGELOG_FILE=changelog.txt"
SET "STATIC_FILE=static.txt"
SET "README=%PLUGIN_DIR%\readme.txt"
SET "TEMP_README=%PLUGIN_DIR%\readme_temp.txt"
SET "DEST_DIR="
SET "DEPLOY_TARGET=github"  REM github or private

REM GitHub settings
SET "GITHUB_REPO=stingray82/MainWP-Bridge-to-Cloudflare-Bridge"
SET "TOKEN_FILE=C:\Ignore By Avast\0. PATHED Items\Plugins\deployscripts\github_token.txt"
SET /P GITHUB_TOKEN=<"%TOKEN_FILE%"
SET "ZIP_NAME=main-wp-to-cloudflare-bridge-extension.zip"

REM ─────────────────────────────────────────────────────
REM STATIC JSON UPDATE CONFIG
REM ─────────────────────────────────────────────────────
SET "STATIC_REPO_DIR=C:\Users\Nathan\Git\example-static-update\MainWP-Bridge-to-Cloudflare-Bridge\"
SET "GENERATE_INDEX_SCRIPT=C:\Ignore By Avast\0. PATHED Items\Plugins\deployscripts\generate_index.php"
SET "STATIC_DOMAIN=https://updates.rupwp.uk"
SET "GITHUB_USER=stingray82"

REM ─────────────────────────────────────────────────────
REM VERIFY REQUIRED FILES
REM ─────────────────────────────────────────────────────
IF NOT EXIST "%PLUGIN_FILE%" (
    echo ❌ Plugin file not found: %PLUGIN_FILE%
    pause & exit /b
)
IF NOT EXIST "%CHANGELOG_FILE%" (
    echo ❌ Changelog file not found: %CHANGELOG_FILE%
    pause & exit /b
)
IF NOT EXIST "%STATIC_FILE%" (
    echo ❌ Static readme file not found: %STATIC_FILE%
    pause & exit /b
)

REM ─────────────────────────────────────────────────────
REM RUN HEADER SCRIPT
REM ─────────────────────────────────────────────────────
php "%HEADER_SCRIPT%" "%PLUGIN_FILE%"

REM Extract metadata from plugin headers
for /f "tokens=2* delims=:" %%A in ('findstr /C:"Requires at least:" "%PLUGIN_FILE%"') do for /f "tokens=* delims= " %%X in ("%%A") do set "requires_at_least=%%X"
for /f "tokens=2* delims=:" %%A in ('findstr /C:"Tested up to:" "%PLUGIN_FILE%"') do for /f "tokens=* delims= " %%X in ("%%A") do set "tested_up_to=%%X"
for /f "tokens=2* delims=:" %%A in ('findstr /C:"Version:" "%PLUGIN_FILE%"') do for /f "tokens=* delims= " %%X in ("%%A") do set "version=%%X"
for /f "tokens=2* delims=:" %%A in ('findstr /C:"Requires PHP:" "%PLUGIN_FILE%"') do for /f "tokens=* delims= " %%X in ("%%A") do set "requires_php=%%X"

REM ─────────────────────────────────────────────────────
REM CREATE README.TXT
REM ─────────────────────────────────────────────────────
(
    echo === %PLUIGN_NAME% ===
    echo Contributors: reallyusefulplugins
    echo Donate link: https://reallyusefulplugins.com/donate
    echo Tags: %PLUGIN_TAGS%
    echo Requires at least: %requires_at_least%
    echo Tested up to: %tested_up_to%
    echo Stable tag: %version%
    echo Requires PHP: %requires_php%
    echo License: GPL-2.0-or-later
    echo License URI: https://www.gnu.org/licenses/gpl-2.0.html
    echo.
) > "%TEMP_README%"

type "%STATIC_FILE%" >> "%TEMP_README%"
echo. >> "%TEMP_README%"
echo == Changelog == >> "%TEMP_README%"
type "%CHANGELOG_FILE%" >> "%TEMP_README%"

IF EXIST "%README%" copy "%README%" "%README%.bak" >nul
move /Y "%TEMP_README%" "%README%"

REM ─────────────────────────────────────────────────────
REM GIT COMMIT AND PUSH CHANGES
REM ─────────────────────────────────────────────────────
pushd "%PLUGIN_DIR%"
git add -A

git diff --cached --quiet
IF %ERRORLEVEL% EQU 1 (
    git commit -m "Version %version% Release"
    git push origin main
    echo ✅ Git commit and push complete.
) ELSE (
    echo ⚠️ No changes to commit.
)
popd

REM ─────────────────────────────────────────────────────
REM ZIP PLUGIN FOLDER
REM ─────────────────────────────────────────────────────
SET "SEVENZIP=C:\Program Files\7-Zip\7z.exe"
for %%a in ("%PLUGIN_DIR%") do (
    set "PARENT_DIR=%%~dpa"
    set "FOLDER_NAME=%%~nxa"
)
SET "ZIP_FILE=%PARENT_DIR%%ZIP_NAME%"

pushd "%PARENT_DIR%"
"%SEVENZIP%" a -tzip "%ZIP_FILE%" "%FOLDER_NAME%"
popd
echo Zipped to: %ZIP_FILE%

REM ─────────────────────────────────────────────────────
REM GENERATE STATIC index.json AND COMMIT TO STATIC REPO
REM ─────────────────────────────────────────────────────
echo Generating static index.json...

SET "PLUGIN_FOLDER_NAME=%FOLDER_NAME%"
SET "PLUGIN_STATIC_PATH=%STATIC_REPO_DIR:~0,-1%"

IF NOT EXIST "%PLUGIN_STATIC_PATH%" (
    mkdir "%PLUGIN_STATIC_PATH%"
)

php "%GENERATE_INDEX_SCRIPT%" ^
    "%PLUGIN_FILE%" ^
    "%CHANGELOG_FILE%" ^
    "%PLUGIN_STATIC_PATH%" ^
    "%GITHUB_USER%" ^
    "%STATIC_DOMAIN%"

echo Static JSON generated in: %PLUGIN_STATIC_PATH%\index.json

pushd "%STATIC_REPO_DIR%"
git add -A
git diff --cached --quiet
IF %ERRORLEVEL% EQU 1 (
    git commit -m "%FOLDER_NAME% version %version%"
    git push origin main
    echo Static repo committed and pushed.
) ELSE (
    echo No changes to commit in static repo.
)
popd

REM ─────────────────────────────────────────────────────
REM DEPLOY LOGIC
REM ─────────────────────────────────────────────────────
IF /I "%DEPLOY_TARGET%"=="private" (
    echo 🔄 Deploying to private server...
    copy "%ZIP_FILE%" "%DEST_DIR%"
    echo ✅ Copied to %DEST_DIR%
) ELSE IF /I "%DEPLOY_TARGET%"=="github" (
    CALL :deploy_github
)

goto :done

:done
echo.
echo 🔚 Done. Press any key to exit...
pause >nul
exit




:deploy_github
echo 🚀 Deploying to GitHub...

setlocal enabledelayedexpansion
set "RELEASE_TAG=v%version%"
set "RELEASE_NAME=%version%"
set "BODY_FILE=%TEMP%\changelog_body.json"
set "CHANGELOG_BODY="

echo Creating body file...

for /f "usebackq delims=" %%l in ("%CHANGELOG_FILE%") do (
    set "line=%%l"
    REM Escape backslashes and quotes, then wrap each line in quotes and append \n manually.
    set "line=!line:\=\\!"
    set "line=!line:"=\"!"
    set "CHANGELOG_BODY=!CHANGELOG_BODY!!line!\\n"

)
REM No need to trim, it's a plain string now.

REM Write JSON manually to BODY_FILE using echo per line
> "!BODY_FILE!" echo {
>> "!BODY_FILE!" echo   "tag_name": "!RELEASE_TAG!",
>> "!BODY_FILE!" echo   "name": "!RELEASE_NAME!",
>> "!BODY_FILE!" echo   "body": "!CHANGELOG_BODY!",
>> "!BODY_FILE!" echo   "draft": false,
>> "!BODY_FILE!" echo   "prerelease": false
>> "!BODY_FILE!" echo }



echo -------- BEGIN JSON BODY --------
type "!BODY_FILE!"
echo -------- END JSON BODY ----------

curl -s -w "%%{http_code}" -o "%TEMP%\github_release_response.json" ^
    -H "Authorization: token %GITHUB_TOKEN%" ^
    -H "Accept: application/vnd.github+json" ^
    https://api.github.com/repos/%GITHUB_REPO%/releases/tags/!RELEASE_TAG! > "%TEMP%\github_http_status.txt"

set /p HTTP_STATUS=<"%TEMP%\github_http_status.txt"
set "RELEASE_ID="

if "!HTTP_STATUS!"=="200" (
    for /f "tokens=2 delims=:," %%i in ('findstr /C:"\"id\"" "%TEMP%\github_release_response.json"') do (
        if not defined RELEASE_ID set "RELEASE_ID=%%i"
    )
    set "RELEASE_ID=!RELEASE_ID: =!"
    set "RELEASE_ID=!RELEASE_ID:,=!"
    echo 📝 Release already exists. Updating body...

    curl -s -X PATCH "https://api.github.com/repos/%GITHUB_REPO%/releases/!RELEASE_ID!" ^
        -H "Authorization: token %GITHUB_TOKEN%" ^
        -H "Accept: application/vnd.github+json" ^
        -H "Content-Type: application/json" ^
        --data-binary "@!BODY_FILE!"
) else (
    echo 🆕 Creating new release...

    curl -s -X POST "https://api.github.com/repos/%GITHUB_REPO%/releases" ^
        -H "Authorization: token %GITHUB_TOKEN%" ^
        -H "Accept: application/vnd.github+json" ^
        -H "Content-Type: application/json" ^
        --data-binary "@!BODY_FILE!" > "%TEMP%\github_release_response.json"

    for /f "tokens=2 delims=:," %%i in ('findstr /C:"\"id\"" "%TEMP%\github_release_response.json"') do (
        if not defined RELEASE_ID set "RELEASE_ID=%%i"
    )
    set "RELEASE_ID=!RELEASE_ID: =!"
    set "RELEASE_ID=!RELEASE_ID:,=!"
)

IF NOT DEFINED RELEASE_ID (
    echo ❌ Could not determine release ID.
    type "%TEMP%\github_release_response.json"
    endlocal
    exit /b
)

setlocal enabledelayedexpansion
set "ASSET_ID="
set "MATCHING_ASSET=0"

for /f "usebackq tokens=*" %%L in ("%TEMP%\github_release_response.json") do (
    set "LINE=%%L"
    setlocal enabledelayedexpansion

    REM --- Look for the matching ZIP file name
    echo !LINE! | findstr /C:"\"name\": \"%ZIP_NAME%\"" >nul
    if !errorlevel! == 0 (
        set "MATCHING_ASSET=1"
    )

    REM --- When found, start looking backward for the `id` key
    if !MATCHING_ASSET! == 1 (
        echo !LINE! | findstr /C:"\"id\":" >nul
        if !errorlevel! == 0 (
            for /f "tokens=2 delims=:" %%B in ("!LINE!") do (
                endlocal
                set "ASSET_ID=%%B"
                set "ASSET_ID=%ASSET_ID:,=%"
                set "ASSET_ID=%ASSET_ID: =%"
                goto :found_asset
            )
        )
    )

    endlocal
)
:found_asset
endlocal & set "ASSET_ID=%ASSET_ID%"


if defined ASSET_ID (
    echo Deleting existing asset ID: %ASSET_ID%...
    curl -X DELETE -H "Authorization: token %GITHUB_TOKEN%" ^
         https://api.github.com/repos/%GITHUB_REPO%/releases/assets/%ASSET_ID%
) else (
    echo ⚠️ No matching asset found to delete.
)

echo 📤 Uploading new ZIP...
curl -s -X POST "https://uploads.github.com/repos/%GITHUB_REPO%/releases/!RELEASE_ID!/assets?name=%ZIP_NAME%" ^
    -H "Authorization: token %GITHUB_TOKEN%" ^
    -H "Accept: application/vnd.github+json" ^
    -H "Content-Type: application/zip" ^
    --data-binary "@%ZIP_FILE%"

echo ✅ Deployment complete → github
endlocal
goto :done

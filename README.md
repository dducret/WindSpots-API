# WindSpots-API
WindSpots API for weather data query

WindSpots API is using Luracast Restler 5.0.10 https://packagist.org/packages/luracast/restler

API returns data in json or xml

> **Spot Type:**
> 
> 1 = KITE,
> 2 = WINDSURF,
> 4 = PADDLE,
> 8 = PARAGLIDE,
> 16 = SWIMMING

for API browsing
https://api.windspots.org/explorer/

Hello
-----
https://api.windspots.org/mobile/hello?to=world

https://api.windspots.org/mobile/hello.json?to=world

just for test.

Station Info
------------
https://api.windspots.org/mobile/stationinfo

https://api.windspots.org/mobile/stationinfo.json

Gives the list of stations with the latest data

Station Data
------------
https://api.windspots.org/mobile/stationdata?station=CHGE04&duration=1

https://api.windspots.org/mobile/stationdata.json?station=CHGE04&duration=1

Gives wind information for the last 1, 12 or 24 hours

Station Data Ext
----------------
https://api.windspots.org/mobile/stationdataext?station=CHGE08&last=true

https://api.windspots.org/mobile/stationdataext.json?station=CHGE08&last=true

https://api.windspots.org/mobile/stationdataext.json?station=CHVD12&last=false&from=2022-03-13%2011:00:00&to=2022-03-14%2009:00:00&ten=false

Gives wind information based on criteria. The duration between the start (from) and the end (to) must be a maximum of 24 hours

> last: latest value only (boolean) (in case of true: from, to and ten are ignored)

> from: SQL date fom format YYY-MM-DD HH:MM:SS

> to: SQL date fom format YYY-MM-DD HH:MM:SS

> ten: for ten minutes step (boolean)

Station Forecast
----------------
https://api.windspots.org/mobile/stationforecast?station=CHGE04

Gives wind forecast for the next 18 hours

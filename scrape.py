# As you can see... the python version of mobLaundry is going to be so much shorter and better

from urllib2 import urlopen
from lxml.html import parse
import lxml.etree as etree
import defineVars

def fetchTable(campusCode, hallNum):
    global laundry_alert_prefix
    global laundry_alert_suffix

    # Open Url and Parse
    # Shame the site isn't gzipped, we could be saving them bandwidth
    page = urlopen(laundry_alert_prefix + campusCode + laundry_alert_suffix + hallNum)
    doc = parse(page).getroot()

    # Matches td number with expected laundry data type
    def checkType(x):
      return {
        0: 'mNumber',
        1: 'mType',
        2: 'mStatus',
        3: 'mETA',
      }[x]
    
    # Sometimes the website returns stuff like "finished 3 minutes ago"
    # For cases like this, we need to store something different into our db.
    def dataParser(data, counter):
      if counter == 1:
        data = "Washer" if data.lower().find("washer") != -1 else "Dryer"
      return data 


    # Fetch all the rows of data. Only the 2nd table of width 410 should
    # be considered. Also we want to avoid the first row and last two rows 
    laundryItems = []
    for row in doc.xpath("//table[@width='410'][2]/tr")[1:-2]:
      laundryItem = {}
      for (counter, tds) in enumerate(row.xpath("./td[position()>2]")):
        cell = tds.xpath("./font//text()") or "0"
        laundryItem[checkType(counter)] = dataParser(cell[0].strip(), counter)
      laundryItems.append(laundryItem)
    return laundryItems
    
hey = fetchTable("penn6389", "0")
print hey

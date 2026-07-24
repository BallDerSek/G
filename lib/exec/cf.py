import os, sys, time, json
from seledroid import webdriver
from seledroid.webdriver.common.by import By

driver = webdriver.Chrome(gui=False)
host = sys.argv[1]

def Cloudflare():
	title = driver.title
	if any(sub.lower() in title.lower() for sub in ["cloudflare","just a moment..."]):
		time.sleep(10)
		return False
	else:
		return True

try:
	driver.get(host)
	while not Cloudflare():
		time.sleep(3)
	
	cf_clearance = driver.get_cookie("cf_clearance")
	user_agent = driver.user_agent
except Exception as e:
	print(f"{e}")
finally:
	title = driver.title
	if any(sub.lower() in title.lower() for sub in ["cloudflare","just a moment..."]):
		data = {
		"cf_clearance" : False,
		"user-agent" : user_agent
		}
	else:
		data = {
		"cf_clearance" : cf_clearance.split("=")[1],
		"user-agent" : user_agent
		}
	with open('cf.json', 'w') as file:
		json.dump(data, file, indent=4)
	driver.close()

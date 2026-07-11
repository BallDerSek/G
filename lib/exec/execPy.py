###LIB_EXECPY=26.05
import time, sys, json, warnings, os, tempfile
import random, re, socket, subprocess

from urllib.parse import urlparse, quote 
from pathlib import Path
warnings.filterwarnings("ignore", category=DeprecationWarning)
os.environ["PYTHONWARNINGS"] = "ignore"

def log(msg): print(msg, file=sys.stderr)

# ==========================================
# CLASS: TUNNEL MANAGER
# ==========================================
class GostTunnel:
    def __init__(self, px_data):
        self.px_data = px_data
        self.proc = None
        self.host = None
        self.port = None

    def start(self):
        if not self.px_data: return None, None
        if self.px_data["type"] == "auth":
            self.port = random.randint(10000, 20000)
            proxy_url = self.px_data['url'].strip()
            cmd = ["gost", "-L", f":{self.port}", "-F", f"{proxy_url}"]
            try:
                self.proc = subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
                log(f"opening localport {self.port}...")
                for _ in range(20):
                    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
                        if s.connect_ex(('127.0.0.1', self.port)) == 0: break
                    time.sleep(0.5)
                log("getting localhost 127.0.0.1...")
                time.sleep(10)
                self.host = "127.0.0.1"
                return self.host, self.port
            except Exception as e:
                log(f"error gost: {e}")
                return None, None
        return self.px_data.get("host"), self.px_data.get("port")

    def stop(self):
        if self.proc:
            try:
                self.proc.terminate()
                log("localhost closed")
            except: pass
            self.proc = None

# ==========================================
# CLASS: CORE EXECUTION
# ==========================================
class ExecPy:
    def __init__(self, px_data=None):
        self.px_data = px_data
        self.tunnel = None
        try:
            from seledroid import webdriver
            from seledroid.webdriver.common.by import By
            self.webdriver = webdriver
            self.By = By
        except ImportError:
            log("install Seledroid module and Apk")
            sys.exit(1)

    def _make_driver(self, ua=None):
        target_h, target_p = None, None
        if self.px_data:
            self.tunnel = GostTunnel(self.px_data)
            target_h, target_p = self.tunnel.start()
        
        d = self.webdriver.Chrome(gui=True, pip_mode=True)
        if target_h:
            log(f"proxy => {target_h}:{target_p}")
            d.set_proxy(target_h, target_p)
        if ua: d.user_agent = ua
        return d

    def _is_dead(self, err):
        s = str(err).lower()
        return any(x in s for x in [
            "broken pipe", "errno 32", "applicationclosed", 
            "please close me", "time out", "net::err_", 
            "webpage not available", "proxy_tunnel_failed"
        ])

    def _check_browser_error(self, driver):
        try:
            title = (driver.title or "").lower()
            curr_url = (driver.current_url or "").lower()
            if curr_url.startswith("data:") or "not available" in title:
                return True
            error_keywords = ["problem loading", "site can't be reached", "connection failed"]
            if any(kw in title for kw in error_keywords): return True
            return False
        except: return True

    def _internal_check(self, d):
        #log("Checking IP...")
        d.get("http://api.ipify.org")
        time.sleep(5)
        if self._check_browser_error(d):
            raise RuntimeError("PROXY_TUNNEL_FAILED")
        ip = re.sub(r"<[^>]+>", "", d.page_source or "").strip().split('\n')[0]
        
        #log("Checking UA...")
        d.get("https://httpbin.org/user-agent")
        time.sleep(3)
        raw_ua = d.page_source or ""
        m = re.search(r'"user-agent"\s*:\s*"([^"]+)"', raw_ua, flags=re.I)
        ua_res = m.group(1) if m else "Unknown"
        return {"ip": ip, "user_agent": ua_res}

    def execute(self, fn, url=None, ua=None, cookie_file=None):
        last_err = None
        for i in range(3):
            driver = self._make_driver(ua=ua)
            try:
                check_res = self._internal_check(driver)

                # if url: self.init_session(driver, url, cookie_file=cookie_file)

                return fn(driver)

            except Exception as e:
                last_err = e
                if self._is_dead(e):
                    log(f"failed: {e}. Retrying tunnel...")
                    try: driver.close()
                    except: pass
                    if self.tunnel: self.tunnel.stop()
                    continue
                raise e 
            finally:
                try: driver.close()
                except: pass
                if self.tunnel: self.tunnel.stop()
        
        return {"error": f"{last_err}"}

    def init_session(self, driver, url, cookie_file=None):
        if not cookie_file: return
        u = urlparse(url)
        origin = f"{u.scheme}://{u.netloc}/"
        
        driver.get(origin)
        time.sleep(1)
        cookies = self._load_netscape(cookie_file, url)
        for name, value in cookies:
            try:
                driver.set_cookie(name, value, url=origin)
            except: pass

    def _load_netscape(self, cookie_file, url):
        if not cookie_file or not os.path.exists(cookie_file):
            #log(f"COOKIE_ERROR: File not found: {cookie_file}")
            return []
        
        host = (urlparse(url).hostname or "").lower()
        now = int(time.time())
        cookies = []
        
        try:
            with open(cookie_file, "r", encoding="utf-8", errors="ignore") as f:
                lines = f.readlines()
                for line in lines:
                    line = line.strip()
                    if not line or (line.startswith("#") and not line.startswith("#HttpOnly_")):
                        continue
                    
                    if line.startswith("#HttpOnly_"):
                        line = line[10:]
                    
                    cols = line.split()
                    if len(cols) < 7: continue
                    
                    domain, _, _, _, expires, name, value = cols[:7]
                    
                    try:
                        if int(expires) != 0 and int(expires) < now: continue
                    except: pass
                    
                    clean_domain = domain.lower().lstrip(".")
                    if clean_domain in host:
                        cookies.append((name, value))
            
            #log(f"COOKIE_DEBUG: Loaded {len(cookies)} cookies for {host}")
            return cookies
            
        except Exception as e:
            #log(f"COOKIE_ERROR: {e}")
            return []

    def reactor(self, driver):
        try:
            if random.random() < 1:
                scroll = random.randint(80, 220); y = random.randint(350, 650)
                if random.random() > 0.5: driver.swipe(120, y, 120, y - scroll, speed=1)
                else: driver.swipe(120, y, 120, y + scroll, speed=1)
            if random.random() < 0.8: driver.click_java(random.randint(50, 260), random.randint(80, 320))
        except: pass

    # --- METHODS ---

    def check_only(self, ua_in=None):
        def job(d): return self._internal_check(d)
        return self.execute(job, ua=ua_in)

    def interstitial(self, url, ua=None, cookie_file=None):
        def job(d):
            if cookie_file:
                self.init_session(d, url, cookie_file=cookie_file)

            log(f"Capturing Interstitial: {url}")
            d.get(url)
            
            for _ in range(15):
                title = (d.title or "").lower()
                if title: break
                time.sleep(1)

            def wait_cookie(max_wait=40):
                elapsed = 0
                while elapsed < max_wait:
                    if self._check_browser_error(d): 
                        raise RuntimeError("PROXY_TUNNEL_FAILED")
                    
                    c = d.get_cookie("cf_clearance")
                    if c: 
                        return c
                    
                    time.sleep(1)
                    elapsed += 1
                return None

            clearance = wait_cookie()
            
            if not clearance:
                d.get(url)
                time.sleep(5)
                clearance = wait_cookie(max_wait=20)

            waf = d.get_cookie("waf_what_a_faucet_session")
            
            return {
                "waf": waf if waf else "", 
                "cf_clearance": clearance if clearance else "", 
                "user_agent": d.user_agent, 
                "cookie": d.get_cookies()
            }
        
        return self.execute(job, url=url, ua=ua, cookie_file=cookie_file)

    def turnstile(self, url, ua=None, cookie_file=None):
        def job(d):
            log(f"Capturing Turnstile: {url}")
            d.get(url)
            self.reactor(d)
            for i in range(60):
                if self._check_browser_error(d): raise RuntimeError("PROXY_TUNNEL_FAILED")
                try:
                    val = d.find_element(self.By.NAME, "cf-turnstile-response").get_attribute("value")
                    if val and len(val) > 20: return {"token": val}
                except: pass
                time.sleep(1)
            return {"token": None}
        return self.execute(job, url=url, ua=ua, cookie_file=cookie_file)

    def recaptcha3(self, url, action, ua=None, cookie_file=None):
        def job(d):
            log(f"Capturing Recaptcha3: {url}")
            d.get(url)
            
            token = None
            for i in range(50):
                if self._check_browser_error(d): raise RuntimeError("PROXY_TUNNEL_FAILED")
                try:
                    token = d.get_recaptcha_v3_token(action=action)
                    if token and len(token) > 50:
                        break
                except:
                    pass
                time.sleep(0.6)
            
            return {"token": token}
        
        return self.execute(job, url=url, ua=ua, cookie_file=cookie_file)


# ==========================================
# MAIN ENTRY
# ==========================================
def pop_px_arg(argv):
    if "--px" not in argv: return None
    i = argv.index("--px"); raw = argv[i + 1].strip()
    del argv[i:i+2]
    if "@" in raw: return {"type": "auth", "url": raw}
    elif ":" in raw:
        h, p = raw.rsplit(":", 1)
        return {"type": "plain", "host": h.strip(), "port": int(p.strip())}
    return None

if __name__ == "__main__":
    try:
        #log(f" ARGS: {sys.argv}")
        
        px_data = pop_px_arg(sys.argv)
        
        #log(f" ARGS AFTER PROXY: {sys.argv}")

        if len(sys.argv) < 2:
            log("Usage: python execPy.py <method> [url/action] [--px ...]")
            sys.exit(1)

        method = sys.argv[1].lower()
        app = ExecPy(px_data=px_data)

        if method == "check":
            ua = sys.argv[2] if len(sys.argv) >= 3 else None
            #log(f"UA MASUK: {ua}")
            print(json.dumps(app.check_only(ua_in=ua)))

        elif method == "interstitial":
            url = sys.argv[2]
            ua = sys.argv[3] if len(sys.argv) >= 4 else None
            ck = sys.argv[4] if len(sys.argv) >= 5 else None
            #log(f"UA INTER: {ua}")
            #log(f"CK INTER: {ck}")
            print(json.dumps(app.interstitial(url, ua=ua, cookie_file=ck)))

        elif method == "turnstile":
            url = sys.argv[2]
            ua = sys.argv[3] if len(sys.argv) >= 4 else None
            ck = sys.argv[4] if len(sys.argv) >= 5 else None
            #log(f"UA TURNSTILE: {ua}")
            #log(f"CK TURNSTILE: {ck}")
            print(json.dumps(app.turnstile(url, ua=ua, cookie_file=ck)))

        elif method == "recaptcha3":
            url = sys.argv[2]
            act = sys.argv[3]
            ua = sys.argv[4] if len(sys.argv) >= 5 else None
            ck = sys.argv[5] if len(sys.argv) >= 6 else None
            #log(f"UA RC3: {ua}")
            #log(f"CK RC3: {ck}")
            print(json.dumps(app.recaptcha3(url, act, ua=ua, cookie_file=ck)))

    except KeyboardInterrupt: log("\nAborted")
    except Exception as e:
        log(f"\nFATAL: {e}")
        print(json.dumps({"error": str(e)}))

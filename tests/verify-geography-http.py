"""Actual multipart registration using the local directory, with a disposable adult account."""
from pathlib import Path
import urllib.request, urllib.error, urllib.parse, http.cookiejar
import json, re, uuid

ROOT = Path(__file__).resolve().parent.parent
BASE = 'http://tarhan-wp.local'
manifest = ROOT / '.geography-test.local'
if manifest.exists(): raise RuntimeError('Clean the preceding geography HTTP fixture first')
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(jar))

def request(path, body=None, headers=None):
    req = urllib.request.Request(BASE + path, data=body, headers=headers or {})
    try: response = client.open(req, timeout=15)
    except urllib.error.HTTPError as error: response = error
    return response.status, response.read()

status, page = request('/registration/')
assert status == 200
config = json.loads(re.search(r'window.tarhanParticipant = (\{.*?\});', page.decode())[1])
assert config['geographyEndpoint'].endswith('/geography/')
status, raw = request('/wp-json/tarhan/v1/geography/regions')
assert status == 200 and len(json.loads(raw)['regions']) >= 80
query = urllib.parse.urlencode({'region_id':'484048','query':'Казань'})
status, raw = request('/wp-json/tarhan/v1/geography/localities?' + query)
assert status == 200 and json.loads(raw)['localities'][0]['id'] == '551487'
print('PASS actual directory routes and exact-city search')

marker = uuid.uuid4().hex
email = 'geo_http_' + marker + '@example.invalid'
manifest.write_text(json.dumps({'marker':marker,'email':email}), encoding='utf-8')
payload = {'address_mode':'directory','region_id':'484048','locality_id':'551487','street':'ул. Баумана','house':'12',
 'first_name':'Иван','last_name':'Проверочный','middle_name':'Иванович','birth_date':'2000-01-01','phone':'+79991234567',
 'parent_first_name':'Пётр','parent_last_name':'Проверочный','parent_middle_name':'Петрович','parent_phone':'+79991234567','teacher_name':'Иванов Иван Иванович','teacher_phone':'+79991234567',
 'school_form':'МБОУ','school_type':'Школа','school_number':'12','education':'Абитуриент','email':email,
 'password':'Technical-Password-' + marker,'password_confirmation':'Technical-Password-' + marker,'rules':'on',
 'region':'Поддельный регион','locality':'Поддельный город','district':'Поддельный район'}

def register(data):
    boundary = 'Tarhan' + uuid.uuid4().hex
    chunks=[]
    for name,value in data.items():
        chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
    chunks.append(f'--{boundary}--\r\n'.encode())
    status,raw=request('/wp-json/tarhan/v1/register',b''.join(chunks),
        {'Content-Type':'multipart/form-data; boundary='+boundary,'X-Tarhan-Token':config['token'],'Origin':BASE})
    return status,json.loads(raw)

status,data=register({**payload,'region_id':'524925'})
assert status==400 and data['code']=='geography_locality', data
status,data=register({**payload,'locality_id':'999999999999'})
assert status==400 and data['code']=='geography_locality', data
status,data=register(payload)
assert status==200 and data['success'], data
status,data=register(payload)
assert status==409 and data['code']=='registration_exists',data
print('PASS multipart registration without external key; forged region/city IDs and duplicate rejected')

headers={'Content-Type':'application/json','X-Tarhan-Token':config['token'],'Origin':BASE}
status,raw=request('/wp-json/tarhan/v1/auth/login',json.dumps({'email':email,'password':payload['password']}).encode(),headers)
assert status==200 and json.loads(raw)['success']
status,raw=request('/entrance/')
assert status==200 and 'Вы вошли в аккаунт'.encode() in raw
print('PASS new directory-registered participant can sign in; cleanup is required next')

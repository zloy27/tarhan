"""Actual HTTP cookies, multipart uploads, ownership, logout; local disposable fixtures only."""
from pathlib import Path
import json, re, html, io, zipfile
import urllib.request, urllib.error, http.cookiejar, uuid

ROOT = Path(__file__).resolve().parent.parent
BASE = 'http://tarhan-wp.local'
data = json.loads((ROOT / '.participant-fixtures.local').read_text(encoding='utf-8'))

class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.http = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(self.jar))
        self.config = {}
    def request(self, path, body=None, headers=None):
        req = urllib.request.Request(BASE+path, data=body, headers=headers or {})
        try:
            r = self.http.open(req, timeout=20)
        except urllib.error.HTTPError as e:
            r = e
        raw = r.read()
        return r.status, raw, r.headers
    def page(self, slug):
        status, raw, headers = self.request('/'+slug+'/')
        assert status == 200, (slug,status)
        source = raw.decode('utf-8')
        match = re.search(r'window.tarhanParticipant = (\{.*?\});', source)
        assert match, 'Missing participant configuration'
        self.config = json.loads(match[1])
        assert 'no-store' in headers.get('Cache-Control','') or 'no-cache' in headers.get('Cache-Control','')
        return source
    def api(self, path, payload=None, raw=None, content_type=None):
        headers = {'X-Tarhan-Token':self.config['token'], 'Origin':BASE}
        if self.config.get('nonce'): headers['X-WP-Nonce'] = self.config['nonce']
        body = raw
        if payload is not None:
            body=json.dumps(payload).encode(); content_type='application/json'
        if content_type: headers['Content-Type']=content_type
        status, raw, head = self.request('/wp-json/tarhan/v1/'+path, body, headers)
        return status, json.loads(raw), head
    def login(self, user):
        self.page('entrance')
        status,result,_=self.api('auth/login', {'email':user['email'],'password':user['password']})
        assert status==200 and result['success'], (status,result)
        self.page('essay')

def multipart(name, content):
    boundary='Tarhan'+uuid.uuid4().hex
    body=(f'--{boundary}\r\nContent-Disposition: form-data; name="essay_file"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n').encode()+content+f'\r\n--{boundary}--\r\n'.encode()
    return body, 'multipart/form-data; boundary='+boundary

def upload(client,name,content):
    body,kind=multipart(name,content)
    return client.api('essay/submit',raw=body,content_type=kind)

guest=Client(); guest.page('entrance')
for slug in ('forgot-password','reset-password'):
    source=guest.page(slug)
    assert re.search(r'participant-dist/assets/password-[^"\s]+\.js',source), 'Missing password module: '+slug
    assert re.search(r'participant-dist/assets/password-[^"\s]+\.css',source), 'Missing password styles: '+slug
guest.page('entrance')
status,raw,_=guest.request('/wp-json/tarhan/v1/auth/login', b'{}', {'Content-Type':'application/json'})
assert status==403, 'Guest CSRF not required'
assert guest.api('essay/status')[0]==401
assert guest.api('address',{'query':'Казань дом'})[0]==503, 'Missing provider must fail visibly'
print('PASS guest permissions, browser token and missing external provider')

a=Client(); a.login(data['users'][0])
for slug in ('forgot-password','reset-password'):
    source=a.page(slug)
    assert a.config['loggedIn'] and re.search(r'participant-dist/assets/password-[^"\s]+\.js',source), 'Logged-in password form is not connected'
a.page('essay')
assert a.api('essay/status')[1]['can_submit']
for name,content in [('bad.pdf',b'<html>not pdf</html>'),('bad.docx',b'PKfake'),('bad.pdf.exe',b'%PDF-1.4'),('empty.txt',b''),('large.txt',b'x'*1048577)]:
    status,result,_=upload(a,name,content)
    assert status==400, (name,status,result)
print('PASS actual multipart rejects renamed, malformed, empty and oversized uploads')
content='Техническая работа. Кириллица сохраняется.'.encode('utf-8')
status,result,_=upload(a,'ESSAY.TXT',content)
assert status==200 and result['submission']['file']['name']=='ESSAY.TXT', result
link=html.unescape(result['submission']['file']['url']).removeprefix(BASE)
status,download,headers=a.request(link)
assert status==200 and download==content and headers['X-Content-Type-Options']=='nosniff'
assert headers['Content-Disposition'].startswith('attachment;')
assert b'tarhan-private' not in json.dumps(result).encode()
status,again,_=upload(a,'replace.txt',b'Replacement')
assert status==200 and again['submission']['file']['name']=='ESSAY.TXT'
assert guest.request(link)[0] != 200
print('PASS stored file download, attachment headers, repeated submission and guest isolation')

b=Client(); b.login(data['users'][1])
assert b.request(link)[0]==403, 'Other participant can download file'
status,result,_=b.api('essay/submit',raw=b'text='+urllib.parse.quote('Текст второго участника').encode(),content_type='application/x-www-form-urlencoded')
assert status==200 and result['submission']['text']=='Текст второго участника', result
assert b.api('essay/status')[1]['submission']['file'] is None
print('PASS actual text submission and cross-account download denial')

c=Client(); c.login(data['users'][2])
assert c.api('essay/status')[1]['allowed'] is False
assert upload(c,'essay.txt',b'Test')[0]==403
print('PASS no test result means no essay upload')

d=Client(); d.login(data['users'][3])
docx=io.BytesIO()
with zipfile.ZipFile(docx,'w') as z:
    z.writestr('[Content_Types].xml','<Types><Override ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>')
    z.writestr('word/document.xml','<document><body>Technical fixture</body></document>')
status,result,_=upload(d,'essay.docx',docx.getvalue())
assert status==200 and result['submission']['file']['name']=='essay.docx',result
assert a.api('auth/logout',payload={})[0]==200
assert a.api('essay/status')[0] in (401,403)
print('PASS actual DOCX upload, logout and revoked cookie session')
print('HTTP verification complete. Fixture cleanup is a separate mandatory step.')

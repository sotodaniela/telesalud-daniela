import base64
import hmac
import hashlib
import json
import time
import urllib.request
import urllib.error

api_key = 'APIBTKhTXviwsK4'
api_secret = 'KH9L2S5D54Ls6fpeEY3HbMeunEKOV6GWBWz3SIjyo8eA'

def b64url(data):
    return base64.urlsafe_b64encode(data).rstrip(b'=').decode()

header = {'alg': 'HS256', 'typ': 'JWT'}
payload = {'iss': api_key, 'exp': int(time.time()) + 3600}

he = b64url(json.dumps(header).encode())
pe = b64url(json.dumps(payload).encode())
sig = hmac.new(api_secret.encode(), f'{he}.{pe}'.encode(), hashlib.sha256).digest()
se = b64url(sig)
token = f'{he}.{pe}.{se}'

# Try different API endpoints
endpoints = [
    '/twirp/livekit.RoomService.CreateRoom',
    '/grpc/livekit.RoomService/CreateRoom',
    '/api/room',
    '/api/v1/rooms',
]

for endpoint in endpoints:
    data = json.dumps({'name': 'vc_prueba001', 'emptyTimeout': 300}).encode()
    req = urllib.request.Request(f'http://localhost:7880{endpoint}', data=data, headers={'Authorization': f'Bearer {token}', 'Content-Type': 'application/json'}, method='POST')
    try:
        with urllib.request.urlopen(req) as r:
            print(f'Success with {endpoint}!')
            print(r.read().decode())
            break
    except urllib.error.HTTPError as e:
        print(f'{endpoint}: HTTP {e.code}')
    except Exception as e:
        print(f'{endpoint}: {e}')

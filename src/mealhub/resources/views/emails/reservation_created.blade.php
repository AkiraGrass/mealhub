<!DOCTYPE html>
<html lang="zh-Hant">
<head><meta charset="utf-8"><title>訂位確認</title></head>
<body>
  <p>您的訂位已建立：</p>
  <ul>
    <li>餐廳：{{ $restaurantName }}</li>
    <li>日期：{{ $date }}</li>
    <li>時段：{{ $timeslot }}</li>
    <li>人數：{{ $partySize }}</li>
  </ul>
  <p>您可透過以下連結查詢訂位狀態：</p>
  <p><a href="{{ $shortLink }}" target="_blank" rel="noopener">{{ $shortLink }}</a></p>
</body>
</html>


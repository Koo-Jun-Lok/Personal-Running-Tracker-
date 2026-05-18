<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($evt['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --theme-color: #8B5CF6; --bg: #F8FAFC; }
        body { background: var(--bg); margin: 0; font-family: 'Inter', sans-serif; padding-bottom: 100px; }
        .container { max-width: 900px; margin: 0 auto; padding: 15px; }
        .event-card { background: white; border-radius: 28px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid #F1F5F9; }
        .event-banner { width: 100%; height: 240px; object-fit: cover; }
        .event-content { padding: 30px; position: relative; }
        .type-badge { position: absolute; top: -15px; left: 30px; background: var(--theme-color); color: white; padding: 6px 16px; border-radius: 12px; font-weight: 800; font-size: 11px; text-transform: uppercase; }
        .title { font-size: 28px; font-weight: 900; margin: 10px 0; }
        
        .progress-box { margin: 20px 0; background: #F5F3FF; padding: 25px; border-radius: 24px; border: 1px solid #DDD6FE; }
        .pg-info { display: flex; justify-content: space-between; font-weight: 800; font-size: 13px; color: var(--theme-color); margin-bottom: 10px; }
        .pg-outer { width: 100%; height: 12px; background: white; border-radius: 10px; overflow: hidden; }
        .pg-inner { height: 100%; background: var(--theme-color); border-radius: 10px; transition: 1.5s cubic-bezier(0.17, 0.67, 0.83, 0.67); }

        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 25px 0; }
        .meta-item { display: flex; align-items: center; gap: 10px; color: #64748B; font-weight: 600; }
        .sync-box { background: #F8FAFC; border: 2px dashed #E2E8F0; border-radius: 24px; padding: 25px; text-align: center; }
        .action-bar { position: fixed; bottom: 0; left: 0; right: 0; background: white; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 -10px 40px rgba(0,0,0,0.05); }
        .btn-main { background: var(--theme-color); color: white; padding: 15px 35px; border-radius: 18px; text-decoration: none; font-weight: 800; border: none; }
    </style>
</head>
<body>
<div class="container">
    <div class="event-card">
        <img src="<?php echo !empty($evt['banner_image']) ? $evt['banner_image'] : '../assets/placeholder.jpg'; ?>" class="event-banner">
        <div class="event-content">
            <div class="type-badge">VIRTUAL CHALLENGE</div>
            <h1 class="title"><?php echo htmlspecialchars($evt['title']); ?></h1>

            <?php if ($participation): ?>
            <div class="progress-box">
                <div class="pg-info">
                    <span>LIVE PROGRESS</span>
                    <span><?php echo number_format($current_km, 2); ?> / <?php echo $target_km; ?> KM</span>
                </div>
                <div class="pg-outer"><div class="pg-inner" style="width: <?php echo $progress_percent; ?>%;"></div></div>
                <div style="text-align: right; margin-top: 10px; font-size: 14px; font-weight: 900; color: var(--theme-color);"><?php echo number_format($progress_percent, 1); ?>%</div>
            </div>
            <?php endif; ?>

            <div class="meta-grid">
                <div class="meta-item"><i class="far fa-calendar-check me-2"></i> <?php echo date("d M", strtotime($evt['event_date'])); ?> - <?php echo date("d M Y", strtotime($evt['end_date'])); ?></div>
                <div class="meta-item"><i class="fas fa-flag-checkered me-2"></i> <?php echo $evt['target_distance_km']; ?> KM</div>
            </div>
            <p style="color: #475569;"><?php echo nl2br(htmlspecialchars($evt['description'])); ?></p>
            
            <div class="sync-box">
                <i class="fas fa-rotate fa-spin mb-3" style="color: var(--theme-color); font-size: 20px;"></i>
                <h4 style="margin:0; font-size:15px;">History Auto-Sync</h4>
                <p style="font-size:12px; color:#64748B; margin-top:5px;">We are counting all your activities recorded since you joined this event.</p>
            </div>
        </div>
    </div>

    <div class="action-bar">
        <a href="<?php echo $back_url; ?>" style="color: #64748B; text-decoration: none; font-weight: 700;">
        <i class="fas fa-arrow-left me-2"></i>Back
    </a>
        <?php if (!$participation): ?>
            <form action="join_event_process.php" method="POST"><input type="hidden" name="event_id" value="<?php echo $event_id; ?>"><button type="submit" class="btn-main">Join Now</button></form>
        <?php else: ?>
            <button class="btn-main" style="<?php echo ($progress_percent >= 100) ? 'background:#10B981;' : 'background:#64748B; opacity:0.8;'; ?> cursor:default;">
                <?php echo ($progress_percent >= 100) ? 'COMPLETED' : 'TRACKING ON'; ?>
            </button>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
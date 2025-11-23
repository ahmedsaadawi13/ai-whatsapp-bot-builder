<!-- FILE: /app/views/dashboard/index.php -->
<div class="dashboard">
    <h1>Dashboard</h1>
    <p>Welcome back, <?php echo View::e($user['name']); ?>!</p>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Bots</h3>
            <p class="stat-number"><?php echo $stats['total_bots']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Channels</h3>
            <p class="stat-number"><?php echo $stats['total_channels']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Contacts</h3>
            <p class="stat-number"><?php echo $stats['total_contacts']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Open Conversations</h3>
            <p class="stat-number"><?php echo $stats['open_conversations']; ?></p>
        </div>
    </div>

    <!-- Message Statistics -->
    <div class="section">
        <h2>Message Statistics (Current Month)</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Messages</h3>
                <p class="stat-number"><?php echo $messageStats['total_messages']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Inbound</h3>
                <p class="stat-number"><?php echo $messageStats['inbound_count']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Outbound</h3>
                <p class="stat-number"><?php echo $messageStats['outbound_count']; ?></p>
            </div>
            <div class="stat-card">
                <h3>AI Replies</h3>
                <p class="stat-number"><?php echo $messageStats['ai_count']; ?></p>
            </div>
        </div>
    </div>

    <!-- Subscription & Usage -->
    <?php if ($subscription): ?>
    <div class="section">
        <h2>Subscription & Usage</h2>
        <div class="subscription-info">
            <p><strong>Plan:</strong> <?php echo View::e($subscription['plan_name']); ?></p>
            <p><strong>Status:</strong> <?php echo View::e($subscription['status']); ?></p>

            <?php if ($usage): ?>
            <h3>Current Usage</h3>
            <ul>
                <li>Channels: <?php echo $usage['total_channels']; ?> / <?php echo $subscription['max_channels']; ?></li>
                <li>Bots: <?php echo $usage['total_bots']; ?> / <?php echo $subscription['max_bots']; ?></li>
                <li>Contacts: <?php echo $usage['total_contacts']; ?> / <?php echo $subscription['max_contacts']; ?></li>
                <li>Messages (this month): <?php echo $usage['total_messages']; ?> / <?php echo $subscription['max_messages_per_month']; ?></li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Conversations -->
    <div class="section">
        <h2>Recent Conversations</h2>
        <?php if (!empty($recentConversations)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Contact</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>Last Message</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentConversations as $conv): ?>
                <tr>
                    <td><?php echo View::e($conv['contact_name'] ?: $conv['phone_number']); ?></td>
                    <td><?php echo View::e($conv['channel_name']); ?></td>
                    <td><span class="badge badge-<?php echo View::e($conv['status']); ?>"><?php echo View::e($conv['status']); ?></span></td>
                    <td><?php echo View::e(date('M d, Y H:i', strtotime($conv['last_message_at']))); ?></td>
                    <td><a href="/conversations/<?php echo $conv['id']; ?>" class="btn btn-small">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No recent conversations.</p>
        <?php endif; ?>
    </div>
</div>

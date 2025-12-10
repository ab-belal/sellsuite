# REDEMPTION SYSTEM TEST PLAN

## UNIT TESTS

### Frontend API Tests
- [ ] POST /wp-json/sellsuite/v1/redeem - Valid redemption
- [ ] POST /wp-json/sellsuite/v1/redeem - Insufficient points
- [ ] POST /wp-json/sellsuite/v1/redeem - Exceeds max redeemable
- [ ] POST /wp-json/sellsuite/v1/redeem - Guest user (should fail)
- [ ] POST /wp-json/sellsuite/v1/redeem - Unauthenticated (should fail)
- [ ] POST /wp-json/sellsuite/v1/redemptions/{id}/cancel - Valid cancellation
- [ ] POST /wp-json/sellsuite/v1/redemptions/{id}/cancel - Wrong user (should fail)
- [ ] POST /wp-json/sellsuite/v1/redemptions/{id}/cancel - Invalid ID (should fail)

### Database Tests
- [ ] Redemption created in wp_sellsuite_point_redemptions
- [ ] Ledger entry created in wp_sellsuite_points_ledger
- [ ] Order meta saved: _points_redeemed_redemption_id
- [ ] Order meta saved: _points_discount_applied
- [ ] Redemption status transitions: applied → pending → completed
- [ ] Redemption status transitions: pending → refunded (on refund)
- [ ] Redemption status transitions: pending → cancelled (on cancel)

## INTEGRATION TESTS

### Checkout Flow
- [ ] Redemption box displays on checkout page
- [ ] Available points show correctly
- [ ] Max redeemable calculated correctly
- [ ] Real-time calculation updates on input
- [ ] Apply button submits AJAX request
- [ ] Redemption row appears in order review table
- [ ] Cancel button removes redemption row
- [ ] Order meta saved when order placed
- [ ] Redemption status = pending after order placed

### Order Lifecycle
- [ ] Order completion triggers redemption completion
- [ ] Redemption status = completed after order complete
- [ ] Ledger status updated to earned
- [ ] Timestamp recorded in completed_at
- [ ] Order refund triggers point restoration
- [ ] Redemption status = refunded after refund
- [ ] New ledger entry created for refund
- [ ] Refund ID stored in redemption record

### Dashboard Display
- [ ] Dashboard shows total redeemed count
- [ ] Dashboard shows active redemptions count
- [ ] Recent redemptions table displays
- [ ] Redemption details show order link
- [ ] Status badges display with correct colors
- [ ] Dates formatted correctly

### Redemption History Page
- [ ] Page accessible from My Account menu
- [ ] Menu item labeled "Redemptions"
- [ ] Page loads with pagination
- [ ] Redemptions listed with all details
- [ ] Status colors display correctly
- [ ] Order links work
- [ ] Pagination controls visible
- [ ] "No redemptions" message shows when empty

## END-TO-END TESTS

### Happy Path
1. User has 200 points
2. Visit checkout page
3. Order total: $100
4. Max redeemable: $20 (20%)
5. Enter 20 points (equals $20)
6. Click Apply
   - EXPECT: Redemption row shows "20 points = $20 discount"
   - EXPECT: Order review updates
7. Place order
   - EXPECT: Points deducted
   - EXPECT: Order meta saved
   - EXPECT: Redemption status = pending
8. Mark order complete
   - EXPECT: Redemption status = completed
   - EXPECT: Timestamp recorded
   - EXPECT: Dashboard updated
9. Check dashboard
   - EXPECT: Total redeemed = 20
   - EXPECT: Recent redemption shown
   - EXPECT: Status = completed
10. Check redemption history page
    - EXPECT: Redemption listed
    - EXPECT: All details correct
    - EXPECT: Link to order works

### Refund Path
1. Complete happy path above
2. Process full refund
   - EXPECT: Refund ID stored
   - EXPECT: Redemption status = refunded
   - EXPECT: New ledger entry created
   - EXPECT: Points restored to user
3. Check user balance
   - EXPECT: +20 points refunded
4. Check dashboard
   - EXPECT: Refund reflected
   - EXPECT: Status = refunded

### Cancel Path
1. Start redemption at checkout
2. Click cancel button
   - EXPECT: Redemption row removed
   - EXPECT: Input box shows again
   - EXPECT: Available points updated
3. Complete order without redemption
   - EXPECT: No redemption record
   - EXPECT: Order processesnormally

### Error Handling
- [ ] 0 or negative points → Error message
- [ ] Points > available → Error message
- [ ] Exceeds max redeemable → Error message
- [ ] Network error → Graceful fallback
- [ ] Server error → Error message displayed
- [ ] Nonce expired → Proper error response

## EDGE CASES

### Multiple Orders
- [ ] User can redeem on separate orders
- [ ] Each order has independent redemption
- [ ] Balance updates correctly across orders
- [ ] Dashboard shows all redemptions

### Partial Refunds
- [ ] Full refund restores points
- [ ] Partial refund noted in records
- [ ] Multiple refunds tracked
- [ ] Ledger shows all entries

### High Volume
- [ ] Handle 1000+ concurrent redemptions
- [ ] Database queries perform well
- [ ] No race conditions
- [ ] Transactions maintain integrity

### Mobile Testing
- [ ] Checkout responsive on mobile
- [ ] Input works on mobile
- [ ] Buttons clickable on mobile
- [ ] Dashboard responsive
- [ ] History page responsive

### Browser Compatibility
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile browsers

## SECURITY TESTS

### Authorization
- [ ] User can't redeem others' points
- [ ] User can't cancel others' redemptions
- [ ] Nonce prevents CSRF
- [ ] Unauthenticated users blocked
- [ ] Guest users handled safely

### Data Validation
- [ ] Points validated before processing
- [ ] Order ID validated
- [ ] User ID verified
- [ ] Prepared statements prevent SQL injection
- [ ] XSS prevention in templates

### Rate Limiting
- [ ] Rapid requests handled
- [ ] No buffer overflow
- [ ] Large numbers handled correctly
- [ ] Null values handled

## PERFORMANCE TESTS

### Speed
- [ ] Redemption applies < 1 second
- [ ] Dashboard loads < 2 seconds
- [ ] History page loads < 2 seconds
- [ ] No N+1 queries
- [ ] Indexes used properly

### Database
- [ ] Single query per operation
- [ ] Prepared statements used
- [ ] Transactions complete atomically
- [ ] No deadlocks
- [ ] Efficient WHERE clauses

### Frontend
- [ ] AJAX calls fast
- [ ] No memory leaks
- [ ] DOM updates efficient
- [ ] CSS renders fast
- [ ] No jank/lag

## MANUAL TESTING CHECKLIST

### Before Release
- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] All E2E tests pass
- [ ] No PHP errors in log
- [ ] No JavaScript errors in console
- [ ] Database integrity checked
- [ ] Backup taken
- [ ] Staging tested
- [ ] Performance benchmarked
- [ ] Security audit passed

### After Release
- [ ] Monitor error logs
- [ ] Check user feedback
- [ ] Monitor performance
- [ ] Verify all features working
- [ ] Customer support notified
- [ ] Documentation updated
- [ ] Release notes published

## REGRESSION TESTS

- [ ] Earning system still works
- [ ] Refund system still works
- [ ] Points system still works
- [ ] Order system unaffected
- [ ] Dashboard functions
- [ ] API endpoints working
- [ ] Hooks firing correctly
- [ ] Database clean

## BROWSER/DEVICE TESTING

### Desktop
- [ ] Windows 10/11 - Chrome
- [ ] Windows 10/11 - Firefox
- [ ] macOS - Chrome
- [ ] macOS - Safari
- [ ] Linux - Chrome

### Mobile
- [ ] iPhone 12+ - Safari
- [ ] Android 12+ - Chrome
- [ ] iPad - Safari
- [ ] Tablet - Chrome

### Network
- [ ] Fast connection (100+ Mbps)
- [ ] Medium connection (20 Mbps)
- [ ] Slow connection (3G)
- [ ] Offline → Online
- [ ] Network interruption

## TEST EXECUTION RESULTS

### Test Date: ___________

### Total Tests: 150+
- Passed: ____
- Failed: ____
- Skipped: ____
- Success Rate: _____%

### Critical Issues: ____
### Major Issues: ____
### Minor Issues: ____

### Tester: ___________
### Sign-off: ___________

## NOTES

_Space for testing notes and issues found_

---

TEST STATUS: PENDING
LAST UPDATED: 2025-12-10

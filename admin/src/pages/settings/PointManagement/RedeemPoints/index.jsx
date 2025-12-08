/**
 * Redeem Points Component
 * 
 * Configure how customers redeem points
 */

import React from 'react';

const RedeemPoints = ({ settings, handleChange }) => {
    return (
        <div className="point-management-tab-panel">
            <h3>Redeeming Points</h3>

            <div className="point-management-field">
                <label className="point-management-label">
                    Maximum Redeemable Percentage (%)
                    <input
                        type="number"
                        min="0"
                        max="100"
                        step="1"
                        value={settings.max_redeemable_percentage}
                        onChange={(e) => handleChange('max_redeemable_percentage', parseInt(e.target.value))}
                        disabled={!settings.points_enabled}
                    />
                </label>
                <p className="point-management-field-description">
                    Maximum percentage of order total that can be paid with points
                </p>
            </div>
        </div>
    );
};

export default RedeemPoints;
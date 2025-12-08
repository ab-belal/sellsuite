/**
 * Point Management Settings Component
 * 
 * Configure points earning, redemption, expiry, and related settings
 */

import React, { useState, useEffect } from 'react';
import General from './PointManagement/General';
import EarningPoints from './PointManagement/EarningPoints';
import RedeemPoints from './PointManagement/RedeemPoints';
import PointExpiry from './PointManagement/PointExpiry';

const PointManagement = () => {
    const [settings, setSettings] = useState({
        points_enabled: true,
        conversion_rate: 1,
        max_redeemable_percentage: 20,
        enable_expiry: false,
        expiry_days: 365,
        point_calculation_method: 'fixed',
        points_per_dollar: 1,
        points_percentage: 0,
    });

    const [activeTab, setActiveTab] = useState('general');
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);

    // Tab configuration
    const tabs = [
        { id: 'general', label: 'General', icon: '⚙️' },
        { id: 'earning', label: 'Earning Points', icon: '💰' },
        { id: 'redeeming', label: 'Redeeming Points', icon: '🎁' },
        { id: 'expiry', label: 'Point Expiry', icon: '⏰' },
    ];

    // Load settings from WordPress
    useEffect(() => {
        if (window.sellsuiteData && window.sellsuiteData.settings) {
            setSettings(prevSettings => ({
                ...prevSettings,
                ...window.sellsuiteData.settings
            }));
        }
    }, []);

    const handleChange = (field, value) => {
        setSettings(prev => ({
            ...prev,
            [field]: value
        }));
    };

    const handleSave = async () => {
        setSaving(true);
        setMessage(null);

        try {
            const response = await fetch(`${window.sellsuiteData.apiUrl}/settings`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.sellsuiteData.nonce
                },
                body: JSON.stringify(settings)
            });

            const data = await response.json();

            if (response.ok) {
                setMessage({ type: 'success', text: 'Settings saved successfully!' });
            } else {
                setMessage({ type: 'error', text: 'Failed to save settings.' });
            }
        } catch (error) {
            setMessage({ type: 'error', text: 'An error occurred while saving.' });
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="point-management">
            {message && (
                <div className={`point-management-message point-management-message--${message.type}`}>
                    {message.text}
                </div>
            )}

            {/* WooCommerce-style Tab Navigation */}
            <div className="point-management-tabs">
                <nav className="point-management-tabs-nav">
                    {tabs.map(tab => (
                        <button
                            key={tab.id}
                            className={`point-management-tab ${activeTab === tab.id ? 'active' : ''} ${
                                !settings.points_enabled && tab.id !== 'general' ? 'disabled' : ''
                            }`}
                            onClick={() => settings.points_enabled || tab.id === 'general' ? setActiveTab(tab.id) : null}
                            disabled={!settings.points_enabled && tab.id !== 'general'}
                        >
                            <span className="point-management-tab-icon">{tab.icon}</span>
                            <span className="point-management-tab-label">{tab.label}</span>
                        </button>
                    ))}
                </nav>

                <div className="point-management-tabs-content">
                    {renderTabContent()}
                </div>
            </div>

            <div className="point-management-actions">
                <button
                    className="point-management-save-button"
                    onClick={handleSave}
                    disabled={saving}
                >
                    {saving ? 'Saving...' : 'Save Settings'}
                </button>
            </div>
        </div>
    );

    // Render content based on active tab
    function renderTabContent() {
        const tabProps = {
            settings,
            handleChange
        };

        switch (activeTab) {
            case 'general':
                return <General {...tabProps} />;
            case 'earning':
                return <EarningPoints {...tabProps} />;
            case 'redeeming':
                return <RedeemPoints {...tabProps} />;
            case 'expiry':
                return <PointExpiry {...tabProps} />;
            default:
                return <General {...tabProps} />;
        }
    }
};

export default PointManagement;

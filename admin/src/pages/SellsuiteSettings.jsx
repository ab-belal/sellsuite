/**
 * SellSuite Settings Component
 * 
 * Main settings container with tab navigation (left) and content area (right)
 */

import React, { useState } from 'react';
import PointManagement from './settings/PointManagement.jsx';
import GeneralSettings from './settings/GeneralSettings.jsx';

const SellsuiteSettings = () => {
    const [activeTab, setActiveTab] = useState('point-management');

    const tabs = [
        {
            id: 'point-management',
            label: 'Point Management',
            icon: '⭐',
            component: PointManagement,
            title: 'Point Management Settings',
            description: 'Configure how customers earn and redeem points'
        },
        // Add more tabs here in the future "general" tab is just for test purpose
        { 
            id: 'general', 
            label: 'General', 
            icon: '⚙️', 
            component: GeneralSettings,
            title: 'General Settings',
            description: 'Configure general settings for your store'
        },
    ];

    const ActiveComponent = tabs.find(tab => tab.id === activeTab)?.component;
    const activeTabData = tabs.find(tab => tab.id === activeTab);

    return (
        <div className="sellsuite-settings">
            <div className="sellsuite-settings-header">
                <h1>{activeTabData?.title || 'SellSuite Settings'}</h1>
                <p>{activeTabData?.description || 'Configure your settings'}</p>
            </div>

            <div className="sellsuite-settings-content">
                {/* Left Sidebar - Tab Navigation */}
                <div className="sellsuite-settings-sidebar">
                    <nav className="sellsuite-settings-nav">
                        {tabs.map(tab => (
                            <button
                                key={tab.id}
                                className={`sellsuite-settings-tab ${activeTab === tab.id ? 'active' : ''}`}
                                onClick={() => setActiveTab(tab.id)}
                            >
                                <span className="sellsuite-settings-tab-icon">{tab.icon}</span>
                                <span className="sellsuite-settings-tab-label">{tab.label}</span>
                            </button>
                        ))}
                    </nav>
                </div>

                {/* Right Content Area */}
                <div className="sellsuite-settings-main">
                    {ActiveComponent ? <ActiveComponent /> : <div>Select a setting</div>}
                </div>
            </div>
        </div>
    );
};

export default SellsuiteSettings;

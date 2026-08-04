# PRD Structure Reference — Trading Bot Domain

This reference captures the full v2.0 section structure generated during analysis of a trading bot PRD.

## 17-Section Structure

### §1 Overview
- **Purpose**: One-paragraph elevator pitch
- **Vision**: One-sentence north star
- **Goals**: Bullet list
- **Key Differentiators**: Table format — what makes this product unique

### §2 Target Users & Personas
- **User Segments Table**: Role, Pain Point, Kebutuhan, Willingness to Pay
- **Detailed Personas**: 3 personas minimum (beginner, professional, investor) with name, age, modal, pain, goal, journey

### §3 Market Analysis & Competitive Landscape
- **Market Size**: Global + regional growth with CAGR
- **Competitor Matrix**: 5-6 competitors with Strengths / Weaknesses / Our Advantage
- **Timeframe Standardization**: Standard industry timeframes (1m, 5m, 15m, 1H, 4H, 1D, 1W, 1M)

### §4 Business Model & Monetization
- **Tiered Subscription**: 4 tiers (Free / Starter / Pro / Enterprise)
- **Alternative Monetization**: Performance fee, marketplace revenue share, add-ons
- **Revenue Projection**: Year 1 target, revenue mix, ARR

### §5 Core Features
- User Management, Exchange Integration, Trading Strategies (pre-built + custom + templates + versioning)
- Dashboard & Analytics, Risk Management (circuit breaker, kill switch, correlation, slippage), Notifications

### §6 AI & Intelligence Layer
- Market Regime Detector, Strategy Recommender, Sentiment Analysis, Anomaly Detection

### §7 Technical Architecture
- Diagram (4 layers), Tech Stack Table, Trading Engine Design, Data Flow, Performance Requirements

### §8 Data Model
- 15+ SQL entities covering users, sessions, bots, trades, positions, strategies, risk, market data, notifications, audit, billing

### §9 Non-Functional Requirements
- 10+ categories: scalability, availability, latency, security, compliance, auditability, durability, observability, recovery, data retention, localization

### §10 Testing Strategy
- Testing Pyramid (4 levels), Backtesting Validation, Chaos Scenarios (6-7), Sandbox Exchange

### §11 Compliance & Legal
- KYC/AML (5 levels), Regulatory Coverage (6 regions), GDPR, Travel Rule, Audit Trail

### §12 Onboarding & Education
- Risk Quiz, Paper Trading (7-day), Setup Wizard, Academy (10 modules), Gamification (8 achievements)

### §13 Milestones & Release Plan
- 5 Phases with 4 sprints each + go-live criteria

### §14 Risks & Mitigations
- Scored matrix: 10 risks with Impact x Probability = Risk Score

### §15 Success Metrics & KPIs
- Business (10), Product (10), Technical (8) metrics

### §16 Appendix
- Terminology (15+ terms), References (10+), Assumptions & Constraints

### §17 Document Changelog
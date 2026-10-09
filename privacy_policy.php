<?php
// privacy_policy.php
// Data Privacy Notice for PinnaQuest, in compliance with the
// Data Privacy Act of 2012 (Republic Act No. 10173).
// Publicly accessible (no login required) so it can be linked from the
// registration form's consent checkbox.
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PinnaQuest | Data Privacy Notice</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;700;800&family=Inter:wght@400;500;600&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
    <style>
        :root {
            --primary-green: #1db968;
            --dark-green: #1a4d2e;
            --text-dark: #2d3748;
            --text-light: #718096;
            --bg-light: #f9fbfb;
            --border-color: #e2e8f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Inter", sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.7;
        }

        /* ── Navbar ── */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 60px;
            background: white;
            border-bottom: 1px solid var(--border-color);
        }
        .navbar .logo {
            font-family: "Lexend", sans-serif;
            font-weight: 800;
            font-size: 20px;
            color: var(--dark-green);
            text-decoration: none;
        }
        .navbar a.back-link {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-light);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .navbar a.back-link:hover { color: var(--primary-green); }

        /* ── Container ── */
        .container {
            max-width: 820px;
            margin: 0 auto;
            padding: 50px 30px 100px;
        }

        .doc-header {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid var(--border-color);
        }
        .doc-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #e6f6ee;
            color: var(--primary-green);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .doc-header h1 {
            font-family: "Lexend", sans-serif;
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark-green);
            margin-bottom: 10px;
        }
        .doc-header p.subtitle {
            color: var(--text-light);
            font-size: 14px;
        }
        .doc-header p.effective-date {
            color: var(--text-light);
            font-size: 13px;
            margin-top: 6px;
            font-style: italic;
        }

        /* ── Table of contents ── */
        .toc {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 40px;
        }
        .toc h3 {
            font-family: "Lexend", sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: var(--dark-green);
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .toc ol { padding-left: 20px; }
        .toc li { margin-bottom: 8px; font-size: 14px; }
        .toc a {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
        }
        .toc a:hover { color: var(--primary-green); text-decoration: underline; }

        /* ── Sections ── */
        section.policy-section {
            margin-bottom: 36px;
            scroll-margin-top: 20px;
        }
        section.policy-section h2 {
            font-family: "Lexend", sans-serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark-green);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        section.policy-section h2 i {
            color: var(--primary-green);
            font-size: 1.05rem;
        }
        section.policy-section p {
            margin-bottom: 14px;
            font-size: 15px;
            color: var(--text-dark);
        }
        section.policy-section ul, section.policy-section ol.lettered {
            margin: 0 0 14px 22px;
        }
        section.policy-section li {
            margin-bottom: 8px;
            font-size: 15px;
        }
        section.policy-section strong { color: var(--dark-green); }

        /* Data table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0 20px;
            font-size: 13.5px;
        }
        .data-table th {
            background: #f0fff4;
            color: var(--dark-green);
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            border: 1px solid var(--border-color);
        }
        .data-table td {
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            vertical-align: top;
        }

        /* Highlight box */
        .highlight-box {
            background: #f0fff4;
            border-left: 4px solid var(--primary-green);
            border-radius: 10px;
            padding: 16px 20px;
            margin: 16px 0;
            font-size: 14px;
        }
        .warning-box {
            background: #fffaf0;
            border-left: 4px solid #f6ad55;
            border-radius: 10px;
            padding: 16px 20px;
            margin: 16px 0;
            font-size: 14px;
        }

        /* Contact card */
        .contact-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px 28px;
        }
        .contact-card p { margin-bottom: 6px; font-size: 14px; }
        .contact-card i { width: 20px; color: var(--primary-green); }

        footer.doc-footer {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-light);
            font-size: 12.5px;
            border-top: 1px solid var(--border-color);
            margin-top: 40px;
        }

        @media (max-width: 600px) {
            .navbar { padding: 16px 20px; }
            .container { padding: 30px 18px 80px; }
            .doc-header h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="loginpanel.php" class="logo">PinnaQuest</a>
    <a href="loginpanel.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Login
    </a>
</nav>

<div class="container">

    <div class="doc-header">
        <span class="doc-badge"><i class="fa-solid fa-shield-halved"></i> Data Privacy Notice</span>
        <h1>PinnaQuest Data Privacy Notice</h1>
        <p class="subtitle">
            This notice explains how PinnaQuest collects, uses, stores, and protects your
            personal data in compliance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>
            of the Republic of the Philippines and its Implementing Rules and Regulations.
        </p>
        <p class="effective-date">Last updated: <?php echo date('F d, Y'); ?></p>
    </div>

    <div class="toc">
        <h3>On this page</h3>
        <ol>
            <li><a href="#collect">1. What Information We Collect</a></li>
            <li><a href="#purpose">2. Why We Collect Your Information</a></li>
            <li><a href="#legal-basis">3. Legal Basis for Processing</a></li>
            <li><a href="#sharing">4. Who Can See Your Information</a></li>
            <li><a href="#retention">5. How Long We Keep Your Data</a></li>
            <li><a href="#security">6. How We Protect Your Data</a></li>
            <li><a href="#rights">7. Your Rights as a Data Subject</a></li>
            <li><a href="#minors">8. Data of Minors</a></li>
            <li><a href="#cookies">9. Cookies and Session Data</a></li>
            <li><a href="#changes">10. Changes to This Notice</a></li>
            
        </ol>
    </div>

    <section class="policy-section" id="collect">
        <h2><i class="fa-solid fa-database"></i> 1. What Information We Collect</h2>
        <p>When you create and use a PinnaQuest account, we collect the following categories of personal information:</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:28%;">Data Category</th>
                    <th style="width:40%;">Examples</th>
                    <th>When Collected</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Account Information</strong></td>
                    <td>Full name, email address, hashed password, role (student/teacher)</td>
                    <td>At registration</td>
                </tr>
                <tr>
                    <td><strong>Profile Information</strong></td>
                    <td>Display name, chosen avatar</td>
                    <td>When you edit your Quest Persona</td>
                </tr>
                <tr>
                    <td><strong>Learning Activity</strong></td>
                    <td>Quiz scores, correct/incorrect answers, XP earned, achievements, streaks</td>
                    <td>Every time you take a quiz or join a Synchro-Quiz session</td>
                </tr>
                <tr>
                    <td><strong>Session Data</strong></td>
                    <td>Synchro-Quiz nickname, room codes joined, participation timestamps</td>
                    <td>When joining a live quiz session</td>
                </tr>
                <tr>
                    <td><strong>Uploaded Content</strong></td>
                    <td>PDF study materials uploaded by teachers or students</td>
                    <td>When uploading materials</td>
                </tr>
                <tr>
                    <td><strong>Technical Data</strong></td>
                    <td>Session cookies used to keep you logged in</td>
                    <td>Throughout your active session</td>
                </tr>
            </tbody>
        </table>

        <div class="highlight-box">
            <i class="fa-solid fa-circle-info"></i>
            We do <strong>not</strong> collect sensitive personal information such as your religion, health records,
            or government-issued ID numbers. We also do not require payment or financial information.
        </div>
    </section>

    <section class="policy-section" id="purpose">
        <h2><i class="fa-solid fa-bullseye"></i> 2. Why We Collect Your Information</h2>
        <p>We collect and process your personal data strictly for the following purposes:</p>
        <ul>
            <li>To create and maintain your PinnaQuest account and verify your identity when you log in.</li>
            <li>To generate, deliver, and score quizzes based on materials you or your teacher upload.</li>
            <li>To track your learning progress, XP, levels, and achievements over time.</li>
            <li>To power gamified features such as the Mission Map, leaderboards, and Synchro-Quiz sessions.</li>
            <li>To allow teachers to monitor the progress of their own students through session and progress reports.</li>
            <li>To communicate important account or service notices (e.g. password changes, security alerts).</li>
            <li>To improve the platform's features, performance, and educational effectiveness.</li>
        </ul>
        <p>We do not use your personal data for advertising, and we do not sell your information to third parties.</p>
    </section>

    <section class="policy-section" id="legal-basis">
        <h2><i class="fa-solid fa-scale-balanced"></i> 3. Legal Basis for Processing</h2>
        <p>Under the Data Privacy Act of 2012, we process your personal data based on:</p>
        <ul>
            <li><strong>Consent</strong> — you agree to this notice and to our processing of your data when you check the consent box during registration.</li>
            <li><strong>Legitimate purpose</strong> — processing is necessary to provide the educational and gamified services you sign up for.</li>
            <li><strong>Contractual necessity</strong> — certain data (like your login credentials) is required to operate your account at all.</li>
        </ul>
        <p>You may withdraw your consent at any time by requesting the deletion of your account (see Section 7).</p>
    </section>

    <section class="policy-section" id="sharing">
        <h2><i class="fa-solid fa-users"></i> 4. Who Can See Your Information</h2>
        <p>Your personal data is only made available to the following parties, and only to the extent necessary:</p>
        <ul>
            <li><strong>Your teacher(s)</strong> — can view your quiz results, Synchro-Quiz performance, and progress reports if you participate in their sessions.</li>
            <li><strong>Other students</strong> — can only see your chosen <em>nickname/display name</em>, avatar, and score on leaderboards and Synchro-Quiz sessions. Your real name and email address are <strong>never</strong> shown to other students.</li>
            <li><strong>System administrators</strong> — authorized personnel who maintain the platform may access data solely for technical support, security, and troubleshooting purposes.</li>
        </ul>
        <div class="warning-box">
            <i class="fa-solid fa-triangle-exclamation"></i>
            We do not share, rent, or sell your personal data to advertisers, marketers, or any unrelated third party.
        </div>
    </section>

    <section class="policy-section" id="retention">
        <h2><i class="fa-solid fa-clock-rotate-left"></i> 5. How Long We Keep Your Data</h2>
        <p>We retain your personal data only for as long as necessary to fulfill the purposes described in this notice, or as required by applicable law. Specifically:</p>
        <ul>
            <li>Account information is kept for as long as your account remains active.</li>
            <li>Quiz results and activity logs are kept to preserve your learning history and progress tracking.</li>
            <li>If you request account deletion, we will anonymize or securely remove your personal information, subject to any records we are legally required to retain (e.g. for academic or administrative record-keeping by your institution).</li>
        </ul>
    </section>

    <section class="policy-section" id="security">
        <h2><i class="fa-solid fa-lock"></i> 6. How We Protect Your Data</h2>
        <p>We implement reasonable and appropriate organizational, physical, and technical security measures to protect your personal data, including:</p>
        <ul>
            <li>Passwords are never stored in plain text — they are hashed using industry-standard algorithms.</li>
            <li>Access to student and teacher data is restricted to authorized roles only.</li>
            <li>Session-based authentication is used to prevent unauthorized access to accounts.</li>
        </ul>
        <p>While we take reasonable steps to protect your data, no method of electronic transmission or storage is 100% secure. We encourage you to use a strong, unique password and to never share your login credentials with others.</p>
    </section>

    <section class="policy-section" id="rights">
        <h2><i class="fa-solid fa-user-shield"></i> 7. Your Rights as a Data Subject</h2>
        <p>Under the Data Privacy Act of 2012, you have the right to:</p>
        <ul>
            <li><strong>Be informed</strong> that your personal data will be, are being, or have been processed.</li>
            <li><strong>Access</strong> your personal data that we hold about you.</li>
            <li><strong>Correct</strong> any inaccurate or outdated personal data (e.g. through Account Settings).</li>
            <li><strong>Object</strong> to the processing of your personal data, subject to certain exceptions.</li>
            <li><strong>Erasure or blocking</strong> of your personal data upon your request, subject to legal or contractual retention requirements.</li>
            <li><strong>Data portability</strong> — request a copy of your data in an electronic format, where applicable.</li>
            <li><strong>File a complaint</strong> with the National Privacy Commission (NPC) if you believe your data privacy rights have been violated.</li>
        </ul>
        <p>To exercise any of these rights, please contact us using the information in Section 11, or use the account management tools available in your Account Settings page.</p>
    </section>

    <section class="policy-section" id="minors">
        <h2><i class="fa-solid fa-child-reaching"></i> 8. Data of Minors</h2>
        <p>PinnaQuest is intended for use in an academic setting and may be used by students who are minors (under 18 years old). For student users who are minors:</p>
        <ul>
            <li>Registration and use of the platform is presumed to be under the supervision, authorization, or arrangement of the student's school or teacher.</li>
            <li>We collect only the minimum information necessary to provide the educational service (name, email, and learning activity).</li>
            <li>Parents, guardians, or the school may contact us to review, correct, or request the deletion of a minor's data.</li>
        </ul>
    </section>

    <section class="policy-section" id="cookies">
        <h2><i class="fa-solid fa-cookie-bite"></i> 9. Cookies and Session Data</h2>
        <p>PinnaQuest uses session cookies solely to keep you logged in while you use the platform. These cookies:</p>
        <ul>
            <li>Do not track your activity outside of PinnaQuest.</li>
            <li>Are automatically deleted or expire when you log out or close your browser session.</li>
            <li>Are not used for advertising or shared with third-party ad networks.</li>
        </ul>
    </section>

    <section class="policy-section" id="changes">
        <h2><i class="fa-solid fa-file-pen"></i> 10. Changes to This Notice</h2>
        <p>We may update this Data Privacy Notice from time to time to reflect changes in our practices or for legal, operational, or regulatory reasons. Any changes will be posted on this page with an updated "Last updated" date. We encourage you to review this notice periodically.</p>
    </section>

   

</div>

<footer class="doc-footer">
    &copy; <?php echo date('Y'); ?> PinnaQuest. This Data Privacy Notice is provided in compliance with
    Republic Act No. 10173, the Data Privacy Act of 2012, and its Implementing Rules and Regulations.
</footer>

</body>
</html>
<?php
/**
 * AI-Powered Interview Tips Generator
 * Uses Claude API to generate personalized interview preparation materials
 */

class InterviewTipsGenerator {
    private $anthropicApiKey;
    private $pdo;
    
    public function __construct($config, $apiKey = null) {
        $this->anthropicApiKey = $apiKey ?? getenv('ANTHROPIC_API_KEY');
        $this->pdo = $this->getConnection($config);
    }
    
    private function getConnection($config) {
        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
            return new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Fetch job description from URL
     */
    public function fetchJobDescription($url) {
        if (empty($url)) {
            return null;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $html = curl_exec($ch);
        curl_close($ch);
        
        if (!$html) {
            return null;
        }
        
        // Extract text content from HTML
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $text = strip_tags($dom->textContent);
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = substr($text, 0, 5000); // Limit to 5000 chars
        
        return $text;
    }
    
    /**
     * Fetch LinkedIn profile information
     */
    public function fetchLinkedInInfo($linkedinUrl) {
        if (empty($linkedinUrl)) {
            return null;
        }
        
        // Extract name from URL as fallback
        preg_match('/linkedin\.com\/in\/([^\/\?]+)/', $linkedinUrl, $matches);
        $profileSlug = $matches[1] ?? '';
        
        return [
            'url' => $linkedinUrl,
            'profile_slug' => $profileSlug,
            'note' => 'Review this LinkedIn profile before your interview'
        ];
    }
    
    /**
     * Generate interview tips using Claude API
     */
    public function generateTips($job, $resume) {
        // Prepare context
        $jobDescription = $this->fetchJobDescription($job['url'] ?? '');
        $contacts = json_decode($job['contacts'] ?? '[]', true);
        
        // Prepare contact information
        $contactInfo = [];
        foreach ($contacts as $contact) {
            $info = [
                'name' => $contact['name'],
                'role' => $contact['role'],
                'title' => $contact['title'] ?? ''
            ];
            
            if (!empty($contact['linkedin'])) {
                $info['linkedin'] = $this->fetchLinkedInInfo($contact['linkedin']);
            }
            
            if (!empty($contact['notes'])) {
                $info['notes'] = $contact['notes'];
            }
            
            $contactInfo[] = $info;
        }
        
        // Build prompt for Claude
        $prompt = $this->buildPrompt($job, $resume, $jobDescription, $contactInfo);
        
        // Call Claude API
        $response = $this->callClaudeAPI($prompt);
        
        if (!$response) {
            // Fallback to basic tips if API call fails
            return $this->generateBasicTips($job, $contactInfo);
        }
        
        return $response;
    }
    
    /**
     * Build the prompt for Claude
     */
    private function buildPrompt($job, $resume, $jobDescription, $contactInfo) {
        $prompt = "You are an expert career coach helping someone prepare for a job interview. Generate comprehensive, personalized interview preparation tips.\n\n";
        
        $prompt .= "## JOB DETAILS\n";
        $prompt .= "Company: {$job['company']}\n";
        $prompt .= "Position: {$job['title']}\n";
        $prompt .= "Current Interview Stage: " . ($job['interview_round'] ?: 'Not specified') . "\n";
        $prompt .= "Application Status: {$job['status']}\n\n";
        
        if ($jobDescription) {
            $prompt .= "## JOB DESCRIPTION (from posting)\n";
            $prompt .= $jobDescription . "\n\n";
        }
        
        if ($resume && isset($resume['data'])) {
            $prompt .= "## CANDIDATE'S RESUME\n";
            $prompt .= "Resume file: {$resume['name']}\n";
            $prompt .= "(Consider the candidate's background when providing advice)\n\n";
        }
        
        if (!empty($contactInfo)) {
            $prompt .= "## INTERVIEW CONTACTS\n";
            foreach ($contactInfo as $contact) {
                $prompt .= "- {$contact['name']} ({$contact['role']})";
                if (!empty($contact['title'])) {
                    $prompt .= " - {$contact['title']}";
                }
                $prompt .= "\n";
                if (!empty($contact['notes'])) {
                    $prompt .= "  Notes: {$contact['notes']}\n";
                }
                if (!empty($contact['linkedin'])) {
                    $prompt .= "  LinkedIn: {$contact['linkedin']['url']}\n";
                }
            }
            $prompt .= "\n";
        }
        
        $prompt .= "## REQUESTED OUTPUT\n";
        $prompt .= "Please provide a comprehensive interview preparation guide with the following sections:\n\n";
        $prompt .= "1. **Executive Summary** - Key points to remember\n";
        $prompt .= "2. **Company Research** - What to know about {$job['company']}\n";
        $prompt .= "3. **Role Analysis** - Key requirements and how to demonstrate fit\n";
        $prompt .= "4. **Interview Stage Tips** - Specific advice for the current interview round\n";
        $prompt .= "5. **Likely Questions** - Common questions for this role with suggested approaches\n";
        $prompt .= "6. **Questions to Ask** - Thoughtful questions to ask interviewers\n";
        $prompt .= "7. **Contact Intelligence** - Tips for engaging with each interviewer\n";
        $prompt .= "8. **Final Checklist** - Pre-interview preparation checklist\n\n";
        $prompt .= "Format the response in clear, readable sections with actionable advice.";
        
        return $prompt;
    }
    
    /**
     * Call the Claude API
     */
    private function callClaudeAPI($prompt) {
        if (!$this->anthropicApiKey) {
            return null;
        }
        
        $data = [
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->anthropicApiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Claude API error: HTTP $httpCode - $response");
            return null;
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['content'][0]['text'])) {
            return $result['content'][0]['text'];
        }
        
        return null;
    }
    
    /**
     * Generate basic tips without AI
     */
    private function generateBasicTips($job, $contactInfo) {
        $tips = [];
        
        // Executive Summary
        $tips[] = [
            'title' => 'Executive Summary',
            'content' => "You're interviewing for the {$job['title']} position at {$job['company']}. " .
                        "Focus on demonstrating your relevant experience and enthusiasm for the role."
        ];
        
        // Company Research
        $tips[] = [
            'title' => 'Company Research',
            'content' => "Before your interview:\n" .
                        "• Visit {$job['company']}'s website and review their mission/values\n" .
                        "• Check recent news and press releases\n" .
                        "• Review their products/services\n" .
                        "• Look up the company on Glassdoor for culture insights\n" .
                        "• Check their LinkedIn page for recent updates"
        ];
        
        // Interview Stage Tips
        $stageTips = $this->getInterviewStageTips($job['interview_round'] ?? '');
        $tips[] = [
            'title' => 'Interview Stage Tips',
            'content' => $stageTips
        ];
        
        // Common Questions
        $tips[] = [
            'title' => 'Likely Questions',
            'content' => "Prepare answers for:\n" .
                        "• Tell me about yourself\n" .
                        "• Why are you interested in this role?\n" .
                        "• Why {$job['company']}?\n" .
                        "• Describe a challenging project you've worked on\n" .
                        "• Where do you see yourself in 5 years?\n" .
                        "• What are your strengths and weaknesses?\n" .
                        "• Tell me about a time you faced conflict at work\n" .
                        "• What questions do you have for us?"
        ];
        
        // Questions to Ask
        $tips[] = [
            'title' => 'Questions to Ask',
            'content' => "Thoughtful questions to ask:\n" .
                        "• What does success look like in this role after 90 days?\n" .
                        "• How would you describe the team culture?\n" .
                        "• What are the biggest challenges facing the team right now?\n" .
                        "• How does {$job['company']} support professional development?\n" .
                        "• What's the typical career path for someone in this role?\n" .
                        "• What do you enjoy most about working here?"
        ];
        
        // Contact Intelligence
        if (!empty($contactInfo)) {
            $contactTips = "Your interviewers:\n\n";
            foreach ($contactInfo as $contact) {
                $contactTips .= "**{$contact['name']}** ({$contact['role']})\n";
                if (!empty($contact['title'])) {
                    $contactTips .= "Title: {$contact['title']}\n";
                }
                if (!empty($contact['notes'])) {
                    $contactTips .= "Notes: {$contact['notes']}\n";
                }
                if (!empty($contact['linkedin'])) {
                    $contactTips .= "• Review their LinkedIn profile before the interview\n";
                    $contactTips .= "• Look for common connections or shared interests\n";
                }
                $contactTips .= "\n";
            }
            $tips[] = [
                'title' => 'Contact Intelligence',
                'content' => $contactTips
            ];
        }
        
        // Final Checklist
        $tips[] = [
            'title' => 'Final Checklist',
            'content' => "Before your interview:\n" .
                        "☐ Research the company thoroughly\n" .
                        "☐ Review the job description\n" .
                        "☐ Prepare your STAR stories (Situation, Task, Action, Result)\n" .
                        "☐ Plan your outfit (professional attire)\n" .
                        "☐ Test your technology (for virtual interviews)\n" .
                        "☐ Prepare copies of your resume\n" .
                        "☐ Plan your route (for in-person interviews)\n" .
                        "☐ Prepare your questions for the interviewer\n" .
                        "☐ Get a good night's sleep\n" .
                        "☐ Arrive 10-15 minutes early"
        ];
        
        return $tips;
    }
    
    /**
     * Get tips for specific interview stages
     */
    private function getInterviewStageTips($stage) {
        $tips = [
            'HR Screen' => "**HR Screening Round**\n\n" .
                "This initial screen focuses on:\n" .
                "• Validating your background and experience\n" .
                "• Discussing salary expectations - research market rates beforehand\n" .
                "• Assessing cultural fit and communication skills\n" .
                "• Confirming your availability and timeline\n\n" .
                "Tips:\n" .
                "• Be concise but personable\n" .
                "• Have your salary range ready (based on research)\n" .
                "• Show genuine enthusiasm for the role\n" .
                "• Ask about next steps and timeline",
            
            'Managers' => "**Hiring Manager Interview**\n\n" .
                "This round focuses on:\n" .
                "• Deep dive into your relevant experience\n" .
                "• Technical or domain-specific competencies\n" .
                "• How you'd approach the role's challenges\n" .
                "• Team fit and working style\n\n" .
                "Tips:\n" .
                "• Prepare specific examples using the STAR method\n" .
                "• Show you understand the role's challenges\n" .
                "• Demonstrate your problem-solving approach\n" .
                "• Ask thoughtful questions about the team and priorities",
            
            'Peers' => "**Peer Interview**\n\n" .
                "This round assesses:\n" .
                "• Collaboration and teamwork skills\n" .
                "• Technical competency from peer perspective\n" .
                "• Communication style and approachability\n" .
                "• Cultural fit with the team\n\n" .
                "Tips:\n" .
                "• Be collaborative and friendly\n" .
                "• Show interest in their experiences\n" .
                "• Discuss how you work with others\n" .
                "• Ask about day-to-day team dynamics",
            
            'Presentation/Demo' => "**Presentation/Demo Round**\n\n" .
                "This round evaluates:\n" .
                "• Your expertise and depth of knowledge\n" .
                "• Communication and presentation skills\n" .
                "• Ability to handle questions and think on your feet\n" .
                "• Preparation and attention to detail\n\n" .
                "Tips:\n" .
                "• Practice your presentation multiple times\n" .
                "• Prepare for likely questions\n" .
                "• Keep it concise and focused\n" .
                "• Have backup slides for anticipated questions\n" .
                "• Test all technology beforehand",
            
            'Executive Team' => "**Executive Interview**\n\n" .
                "This final round focuses on:\n" .
                "• Strategic thinking and vision alignment\n" .
                "• Leadership potential and maturity\n" .
                "• Cultural fit at the organizational level\n" .
                "• Long-term potential and career aspirations\n\n" .
                "Tips:\n" .
                "• Think strategically and big-picture\n" .
                "• Show alignment with company mission/values\n" .
                "• Demonstrate leadership qualities\n" .
                "• Be confident but humble\n" .
                "• Prepare thoughtful questions about company direction"
        ];
        
        return $tips[$stage] ?? 
            "**General Interview Preparation**\n\n" .
            "Regardless of the interview stage:\n" .
            "• Research the company thoroughly\n" .
            "• Prepare STAR stories for behavioral questions\n" .
            "• Review the job description and match your experience\n" .
            "• Prepare thoughtful questions\n" .
            "• Practice active listening\n" .
            "• Follow up with a thank-you note";
    }
    
    /**
     * Generate PDF from tips
     */
    public function generatePDF($tips, $job) {
        $html = $this->generateHTML($tips, $job);
        
        $filename = 'interview_tips_' . preg_replace('/[^a-z0-9]/i', '_', $job['company']) . '_' . time() . '.html';
        $filepath = __DIR__ . '/' . $filename;
        
        file_put_contents($filepath, $html);
        
        return $filename;
    }
    
    /**
     * Generate HTML document from tips
     */
    private function generateHTML($tips, $job) {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Preparation Guide - ' . htmlspecialchars($job['company']) . '</title>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap");
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.7;
            color: #1a1a2e;
            max-width: 800px;
            margin: 0 auto;
            padding: 60px 40px;
            background: #fff;
        }
        
        .header {
            text-align: center;
            margin-bottom: 50px;
            padding-bottom: 30px;
            border-bottom: 3px solid #667eea;
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
        }
        
        .header .meta {
            color: #666;
            font-size: 1.1rem;
        }
        
        .header .meta strong { color: #333; }
        
        .section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        
        .section h2 {
            font-size: 1.4rem;
            color: #667eea;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #eee;
        }
        
        .section .content {
            background: #f8f9fc;
            padding: 25px;
            border-radius: 12px;
            white-space: pre-wrap;
            font-size: 0.95rem;
        }
        
        .section .content strong { color: #333; }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #999;
            font-size: 0.85rem;
        }
        
        @media print {
            body { padding: 40px; }
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Interview Preparation Guide</h1>
        <div class="meta">
            <strong>' . htmlspecialchars($job['company']) . '</strong> &mdash; ' . htmlspecialchars($job['title']) . '<br>
            Generated on ' . date('F j, Y') . '
        </div>
    </div>';
        
        if (is_string($tips)) {
            // AI-generated content
            $html .= '<div class="section">
                <div class="content">' . nl2br(htmlspecialchars($tips)) . '</div>
            </div>';
        } else {
            // Structured tips array
            foreach ($tips as $section) {
                $html .= '
    <div class="section">
        <h2>' . htmlspecialchars($section['title']) . '</h2>
        <div class="content">' . nl2br(htmlspecialchars($section['content'])) . '</div>
    </div>';
            }
        }
        
        $html .= '
    <div class="footer">
        <p>Good luck with your interview! Remember to be yourself and stay confident.</p>
        <p>Generated by Job Application Tracker</p>
    </div>
</body>
</html>';
        
        return $html;
    }
}

// Handle direct API calls
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] === 'generateTips') {
        $config = $input['config'];
        $job = $input['job'];
        $resume = $input['resume'] ?? null;
        $apiKey = $input['apiKey'] ?? null;
        
        $generator = new InterviewTipsGenerator($config, $apiKey);
        $tips = $generator->generateTips($job, $resume);
        $pdfFile = $generator->generatePDF($tips, $job);
        
        echo json_encode([
            'success' => true,
            'pdfUrl' => $pdfFile,
            'tips' => $tips
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ]);
    }
}
?>

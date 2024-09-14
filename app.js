const express = require('express');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const axios = require('axios');
const cron = require('node-cron');
const mysql = require('mysql2/promise');

puppeteer.use(StealthPlugin());

const app = express();
const facebook = express();
const PORT = 3000;

// const pool = mysql.createPool({
//     host: 'localhost',  // Replace with your database host
//     user: 'root',       // Replace with your MySQL username
//     password: '1234567890',  // Replace with your MySQL password
//     database: 'artms',  // Replace with your database name
//   });

  const pool = mysql.createPool({
    host: '127.0.0.1',  // Replace with your database host
    user: 'lavufirst',       // Replace with your MySQL username
    password: 'exZW3jJdGPBhsfQGPMF9',  // Replace with your MySQL password
    database: 'zedinstablod',  // Replace with your database name
  });
function delay(time) {
    return new Promise(resolve => setTimeout(resolve, time));
}

async function sendPushNotification(title, url,content) {
    try {
        var messageit = title+" Wanted for employment at "+content +" for details click apply button ";
        const notificationPayload = {
            title: title,
            message: messageit,
            target_url: url,
            action_buttons: [
                {
                    title: "Apply ",
                    url: url
                }
            ]
        };

        const headers = {
            'Content-Type': 'application/json',
            'webpushrKey': '85aade95109635574f6a9505e015db2a',  // Replace with your actual webpushrKey
            'webpushrAuthToken': '79927'  // Replace with your actual webpushrAuthToken
        };

        const response = await axios.post('https://api.webpushr.com/v1/notification/send/all', notificationPayload, {
            headers: headers
        });

        console.log('Notification sent successfully:', response.data);
    } catch (error) {
        console.error('Error sending push notification:', error);
    }
}

async function sendPushNotificationZambiaJob(title, url,content) {
    try {
        var messageit = title+" Wanted for employment at "+content +" for details click apply button ";
        const notificationPayload = {
            title: title,
            message: messageit,
            target_url: url,
            action_buttons: [
                {
                    title: "Apply ",
                    url: url
                }
            ]
        };

        const headers = {
            'Content-Type': 'application/json',
            'webpushrKey': '8f2c3957928b064389d37d8ce72fb7e9',  // Replace with your actual webpushrKey
            'webpushrAuthToken': '49496'  // Replace with your actual webpushrAuthToken
        };

        const response = await axios.post('https://api.webpushr.com/v1/notification/send/all', notificationPayload, {
            headers: headers
        });

        console.log('Notification sent successfully:', response.data);
    } catch (error) {
        console.error('Error sending push notification:', error);
    }
}

app.get('/scrape-jobs', async (req, res) => {

    let browser;
    let url = 'https://jobsearchzm.com';
    //let url = 'https://gozambiajobs.com';
//https://jobsearchzm.com
//https://gozambiajobs.com

    try {
        browser = await puppeteer.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-infobars',
                '--window-position=0,0',
                '--ignore-certificate-errors',
                '--ignore-certificate-errors-spki-list',
                '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        });

        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

        await page.evaluateOnNewDocument(() => {
            Object.defineProperty(navigator, 'webdriver', { get: () => false });
            Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3, 4, 5] });
            Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en'] });
        });
        await page.goto(url, { waitUntil: 'networkidle2' });

        // Click the load more button twice to load more jobs
        for (let i = 0; i < 2; i++) {
            try {
                console.log(`Clicking load more button - Attempt ${i + 1}`);

                await page.evaluate(() => {
                    document.querySelector('.load_more_jobs').click();
                });
                await delay(10000); // wait for 5 seconds to ensure jobs are loaded

            } catch (error) {
                console.log(`Load more button not found or error occurred on attempt ${i + 1}:`, error);
                break; // if there's an error (button not found), break the loop
            }
        }

        await page.waitForSelector('.job_listings .job_listing');

        const jobLinks = await page.evaluate(() => {
            const links = [];
            const jobElements = document.querySelectorAll('.job_listings .job_listing');
            jobElements.forEach(job => {
                const url = job.querySelector('a').href;
                links.push(url);
            });
            return links;
        });

        console.log(`Total job links found: ${jobLinks.length}`);

        const jobs = [];

        // Mapping job types to their respective IDs       
        const jobTypeMapping = {
            'Freelance': 5,
            'Full Time': 2,
            'Internship': 6,
            'Part Time': 3,
            'Temporary': 4,
            'Consultancy': 86,
            'Contract': 77,
            'Consultant':86
    };
    
    const terms = [
        { id: 36, name: "Copperbelt" },
        { id: 37, name: "Southern" },
        { id: 38, name: "Northern" },
        { id: 39, name: "Muchinga" },
        { id: 40, name: "Central" },
        { id: 41, name: "Eastern" },
        { id: 42, name: "Luapula" },
        { id: 43, name: "Western" },
        { id: 44, name: "North-Western" },
        { id: 45, name: "Lusaka" },
        { id: 46, name: "Ndola" },
        { id: 47, name: "Kitwe" },
        { id: 48, name: "Chingola" },
        { id: 49, name: "Luanshya" },
        { id: 50, name: "Choma" },
        { id: 51, name: "Mazabuka" },
        { id: 52, name: "Monze" },
        { id: 53, name: "Livingstone" },
        { id: 54, name: "Magoye" },
        { id: 55, name: "Kasama" },
        { id: 56, name: "Mbala" },
        { id: 57, name: "Chinsali" },
        { id: 58, name: "Mpika" },
        { id: 59, name: "Isoka" },
        { id: 60, name: "Nakonde" },
        { id: 61, name: "Lusaka" },
        { id: 62, name: "Kabwe" },
        { id: 63, name: "Kapiri" },
        { id: 64, name: "Serenje" },
        { id: 65, name: "Mkushi" }
      ];
      
      function getLocationIds(location) {
        // Filter for all matches and return their IDs
        const matches = terms.filter(term => term.name.toLowerCase() === location.toLowerCase().trim());
        return matches.length > 0 ? matches.map(term => term.id) : [76]; // Return [76] if no match is found
      }

        for (let link of jobLinks) {
            
            const jobPage = await browser.newPage();
            await jobPage.goto(link, { waitUntil: 'networkidle2' });

            const jobData = await jobPage.evaluate(() => {

                const titleElement = document.querySelector('.entry-title');
                const companyElement = document.querySelector('.company_header .name strong');
                const locationElement = document.querySelector('.location a');
                const typeElement = document.querySelector('.job-listing-meta .job-type');
                const dateElement = document.querySelector('.job-listing-meta .date-posted time');
                const deadlineElement = document.querySelector('.job-listing-meta .application-deadline');
                const descriptionElement = document.querySelector('.job_description');
                const logoElement = document.querySelector('.company_logo');
                const mailtoElement = document.querySelector('.job_application_email');
                // Function to extract the email address from a mailto link
                function cleanEmail(mailto) {
                    const mailtoLink = mailto ? mailto.getAttribute('href') : '';
                    if (mailtoLink && mailtoLink.startsWith('mailto:')) {
                        const cleanEmail = mailtoLink.replace('mailto:', '').split('?')[0]; // Remove 'mailto:' and strip everything after '?'
                        return cleanEmail;
                    }
                    return '';
                }
                return {
                    position: titleElement ? titleElement.innerText : null,
                    company: companyElement ? companyElement.innerText : null,
                    location: locationElement ? locationElement.innerText.trim() : null,
                    jobType: typeElement ? typeElement.innerText.trim() : null,
                    datePosted: dateElement ? dateElement.getAttribute('datetime') : null,
                    deadline: deadlineElement ? deadlineElement.innerText.replace('Closes:', '').trim() : null,
                    description: descriptionElement ? descriptionElement.innerText.trim() : null,
                    company_logo: logoElement ? logoElement.src : null,
                    application_email: cleanEmail(mailtoElement)//mailtoElement ? mailtoElement.href : null
                };
            });

            // Skip if job type is "Advert"
            if (jobData.jobType && jobData.jobType.toLowerCase() === 'advert') {
                console.log('Skipping Advert job');
                await jobPage.close();
                continue;  // Skip this job
            }

            // Map the job type to its corresponding ID
            const jobTypeID = jobTypeMapping[jobData.jobType] || null;

            if (!jobTypeID) {
                console.log(`Skipping job with unknown type: ${jobData.jobType}`);
                await jobPage.close();
                continue;
            }

            // Formatting the job data according to your specified JSON structure
            const formattedJob = {
                "status": "publish",
                "type": "job_listing",
                "title": jobData.position || 'Unknown Position',
                "content": jobData.description || 'No Description Provided',
                "protected": true,
                "author": 1,  // Assuming author ID is 1, adjust as needed
                "featured_media": 0,  // Assuming no media, change if needed
                "template": "",
                "meta": {
                    "_promoted": "",
                    "_job_location": jobData.location || 'Zambia',
                    "_application": jobData.application_email || 'https://zinstablog.com',
                    "title": jobData.position || 'Unknown Position',
                    "_company_name": jobData.company || 'Unknown Company',
                    "_company_website": "https://zinstablog.com",  // Placeholder, replace if available
                    "_company_tagline": "",
                    "_company_twitter": "",
                    "_company_video": "",
                    "_filled": 0,
                    "_featured": 0,
                    "_remote_position": 0,
                    "_job_salary":"",  // Placeholder salary, replace if available
                    "_job_salary_currency": "",  // Placeholder currency, adjust as needed
                    "_job_salary_unit": "",
                    "_application_deadline":jobData.deadline,
                    "job-categories": [67,68,69,70,71,72,73,74,75],//[1, 3, 6, 8, 9, 4, 3],  // Adjust job category IDs as necessary
                "job-types": [jobTypeID],  // Adjust job type IDs as necessary
                "job-regions": getLocationIds(jobData.location)  // Adjust job region IDs as necessary
                },
                "job-categories": [67,68,69,70,71,72,73,74,75],//[1, 3, 6, 8, 9, 4, 3],  // Adjust job category IDs as necessary
                "job-types": [jobTypeID],  // Adjust job type IDs as necessary
                "job-types": [2],
                "job_categories": [67,68,69,70,71,72,73,74,75,80,81,82,83,84,85],
                "job-categories": [67,68,69,70,71,72,73,74,75,80,81,82,83,84,85],
                "categories":[67,68,69,70,71,72,73,74,75,80,81,82,83,84,85],
                "job_listing_region": getLocationIds(jobData.location)
            };

            jobs.push(formattedJob);
            await jobPage.close();
        }

        //console.log(jobs);

        // Send each job one by one
        const [existingRecords] = await pool.query('SELECT title, company, application, location FROM jobs limit 80');

        // Step 2: Create a Set for efficient comparison of existing records
        const existingSet = new Set(existingRecords.map(record => `${record.title}-${record.company}-${record.application}-${record.location}`));
    
        // Step 3: Filter out jobs that already exist in the database
        const newJobs = jobs.filter(job => {
          const jobKey = `${job.title}-${job.meta._company_name}-${job.meta._application}-${job.meta._job_location}`;
          return !existingSet.has(jobKey);
        });
    
        console.log(`Filtered new jobs: ${newJobs.length} out of ${jobs.length}`);

        for (let job of newJobs) {
            //console.log(job);
            const query = 'INSERT INTO jobs (title, company, application, location) VALUES (?, ?, ?, ?)';
      await pool.query(query, [job.title, job.meta._company_name, job.meta._application, job.meta._job_location]);

            try {
                const response = await axios.post('https://zedcareers.net/wp-json/wp/v2/job-listings/', job, {
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    auth: {
                        username: 'admin',  // Replace with actual username
                        password: 'BiWM LRlU Gcxb 9w6u XJx0 x79i'   // Replace with actual password 
                    }
                });
                
                var response_link = response.data.link ? response.data.link : 'https://zedcareers.net';
               await sendPushNotificationZambiaJob(job.title, response_link,job.meta._company_name);
            await sendPushNotification(job.title, response_link,job.meta._company_name);

                console.log(`Job sent successfully: ${job.title}`);
            } catch (error) {
                console.error(`Error sending job:`, error);
            }
               
        }
        res.status(200).json({
            message: `All jobs found: ${jobs.length}, and ${newJobs.length} jobs processed and sent one by one`
        });

    } catch (error) {
        console.error('Error in /scrape-jobs:', error);
        res.status(500).send('An error occurred', error);
        
    } finally {
        if (browser) {
            await browser.close();
        }
    }

});


cron.schedule('*/10 * * * *', async () => { // Run every 10 minutes
    try {
        const response = await axios.get(`http://localhost:${PORT}/scrape-jobs`);
        console.log('Scheduled job triggered', response.data);
    } catch (error) {
        console.error('Error triggering scheduled job', error);
    }
});

app.listen(PORT, () => {
    console.log(`Server is running on http://localhost:${PORT}`);
});
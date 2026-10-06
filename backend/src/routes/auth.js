const express = require("express");
const bcrypt = require("bcrypt");
const jwt = require("jsonwebtoken");
const db = require("../db");

const router = express.Router();


// ======================================
// JWT CONFIGURATION
// ======================================

const JWT_SECRET =
    process.env.JWT_SECRET || "veyra-development-secret-key";

const JWT_EXPIRES_IN = "2h";


// ======================================
// REGISTER
// POST /api/auth/register
// ======================================

router.post("/register", async (req, res) => {

    try {

        const { name, email, password } = req.body;


        // Validate required fields
        if (!name || !email || !password) {

            return res.status(400).json({
                success: false,
                message: "Name, email and password are required."
            });

        }


        const cleanName = name.trim();
        const cleanEmail = email.trim().toLowerCase();


        // Validate name
        if (cleanName.length < 2) {

            return res.status(400).json({
                success: false,
                message: "Name must contain at least 2 characters."
            });

        }


        // Validate email
        const emailRegex =
            /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!emailRegex.test(cleanEmail)) {

            return res.status(400).json({
                success: false,
                message: "Please provide a valid email address."
            });

        }


        // Validate password
        if (password.length < 8) {

            return res.status(400).json({
                success: false,
                message: "Password must be at least 8 characters."
            });

        }


        // Check existing account
        const [existingUsers] = await db.query(
            "SELECT id FROM users WHERE email = ?",
            [cleanEmail]
        );


        if (existingUsers.length > 0) {

            return res.status(409).json({
                success: false,
                message: "An account with this email already exists."
            });

        }


        // Hash password
        const passwordHash =
            await bcrypt.hash(password, 12);


        // Create user
        const [result] = await db.query(
            `INSERT INTO users
                (name, email, password_hash)
             VALUES
                (?, ?, ?)`,
            [
                cleanName,
                cleanEmail,
                passwordHash
            ]
        );


        return res.status(201).json({

            success: true,

            message: "Account created successfully.",

            user: {
                id: result.insertId,
                name: cleanName,
                email: cleanEmail,
                role: "user"
            }

        });

    } catch (error) {

        console.error(
            "Registration error:",
            error
        );

        return res.status(500).json({
            success: false,
            message: "Unable to create account."
        });

    }

});


// ======================================
// LOGIN
// POST /api/auth/login
// ======================================

router.post("/login", async (req, res) => {

    try {

        const { email, password } = req.body;


        // Validate required fields
        if (!email || !password) {

            return res.status(400).json({
                success: false,
                message: "Email and password are required."
            });

        }


        const cleanEmail =
            email.trim().toLowerCase();


        // Find user
        const [users] = await db.query(
            `SELECT
                id,
                name,
                email,
                password_hash,
                role
             FROM users
             WHERE email = ?`,
            [cleanEmail]
        );


        // Don't reveal whether email exists
        if (users.length === 0) {

            return res.status(401).json({
                success: false,
                message: "Invalid email or password."
            });

        }


        const user = users[0];


        // Compare password
        const passwordMatches =
            await bcrypt.compare(
                password,
                user.password_hash
            );


        if (!passwordMatches) {

            return res.status(401).json({
                success: false,
                message: "Invalid email or password."
            });

        }


        // Create JWT
        const token = jwt.sign(

            {
                id: user.id,
                email: user.email,
                role: user.role
            },

            JWT_SECRET,

            {
                expiresIn: JWT_EXPIRES_IN
            }

        );


        // Successful login
        return res.status(200).json({

            success: true,

            message: "Login successful.",

            token,

            user: {
                id: user.id,
                name: user.name,
                email: user.email,
                role: user.role
            }

        });

    } catch (error) {

        console.error(
            "Login error:",
            error
        );

        return res.status(500).json({
            success: false,
            message: "Unable to login."
        });

    }

});


// ======================================
// AUTH STATUS
// GET /api/auth/status
// ======================================

router.get("/status", (req, res) => {

    res.json({

        success: true,

        message: "Authentication API is working."

    });

});


module.exports = router;
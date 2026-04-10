-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 09, 2026 at 11:16 AM
-- Server version: 10.7.3-MariaDB-log
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sql_teste`
--

-- --------------------------------------------------------

--
-- Table structure for table `temas`
--

CREATE TABLE `temas` (
  `id` int(11) NOT NULL,
  `nome` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cor_primaria` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '#10b981',
  `cor_secundaria` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '#C850C0',
  `cor_terciaria` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '#FFCC70',
  `cor_fundo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '#0f172a',
  `cor_fundo_claro` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '#1e293b',
  `cor_texto` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '#ffffff',
  `cor_texto_sec` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT 'rgba(255,255,255,0.6)',
  `cor_borda` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT 'rgba(255,255,255,0.06)',
  `cor_sucesso` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '#10b981',
  `cor_erro` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '#dc2626',
  `cor_aviso` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '#f59e0b',
  `cor_info` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '#3b82f6',
  `cor_menu_fundo` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT 'linear-gradient(180deg,#1a1f3a 0%,#0f1429 100%)',
  `css_customizado` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fundo_imagem` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fundo_tipo` enum('gradiente','imagem') COLLATE utf8mb4_unicode_ci DEFAULT 'gradiente',
  `ativo` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `descricao` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `acc1` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '#6366f1',
  `acc2` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '#818cf8',
  `preview_cor` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '#6366f1',
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'padrao'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `temas`
--
ALTER TABLE `temas`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `temas`
--
ALTER TABLE `temas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

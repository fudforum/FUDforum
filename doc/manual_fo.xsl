<?xml version='1.0' encoding='ISO-8859-1' ?>

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
		version="1.0"
		xmlns="http://www.w3.org/TR/xhtml1/transitional"
		execlude-result-prefixes="#default">
		
<xsl:import href="/usr/local/share/docbook-xsl/fo/docbook.xsl"/>

<xsl:param name="generate.toc">
book toc
chapter toc
</xsl:param>

<xsl:param name="insert.xref.page.number" select="1"/>
<!-- <xsl:param name="passivetex.extensions" select="1"/> -->
</xsl:stylesheet>

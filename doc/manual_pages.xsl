<?xml version='1.0' encoding='ISO-8859-1' ?>

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
		version="1.0"
		xmlns="http://www.w3.org/TR/xhtml1/transitional"
		execlude-result-prefixes="#default">
		
<xsl:import href="/usr/local/share/docbook-xsl/html/chunk.xsl"/>

<xsl:param name="generate.toc">
book toc
chapter toc
</xsl:param>

<xsl:param name="base.dir" select="'html/'"/>
<xsl:param name="navig.showtitles">1</xsl:param>
<xsl:param name="chunk.section.depth" select="1"/>
<xsl:param name="use.id.as.filename" select="1"/>
<xsl:param name="default.encoding" select="'ISO-8859-1'"/>

<xsl:param name="label.from.part" select="'1'"/>

</xsl:stylesheet>
